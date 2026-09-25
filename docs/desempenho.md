# Desempenho e cache

## Escopo e consistência

O catálogo público usa `PublicCatalog`. A consulta mantém os filtros por título, ISBN, autor e categoria, ordenação por título/id e páginas de 12 itens. O cache guarda somente os campos públicos selecionados, sem sessão, usuário, token, URL absoluta ou dados de empréstimos. Links de paginação são reconstruídos em cada request.

`CATALOG_CACHE_STORE=catalog` aponta para o serviço **redis-cache**, separado do Redis da fila. O cache tem limite de 128 MB, política `allkeys-lru` e sem persistência; a fila mantém AOF e `noeviction`. Sessões, locks de agendamento, limitação de requisições e revisão do catálogo continuam no cache operacional em banco.

Alterações Eloquent em livro, autor e categoria mudam a revisão do catálogo **após commit**. A sincronização de exemplares atualiza o livro e dispara a mesma invalidação. Rollback não publica a mudança. Escritas em lote por SQL precisam chamar explicitamente `PublicCatalog::invalidate()` após a transação; o seeder de benchmark faz isso. Dentro de uma transação, a leitura ignora o cache para não publicar dados ainda não confirmados.

Cada página expira em **60 segundos**. A revisão fica no banco para que uma indisponibilidade do Redis de cache não perca a invalidação. Uma queda entre commit e callback, ou falha do cache operacional, ainda pode deixar uma indicação antiga até o TTL. Disponibilidade pública é uma indicação; retirada, reserva e renovação continuam usando transação, bloqueios e revalidação no MySQL. Se o Redis de cache falhar, a consulta volta ao banco com timeout de conexão/leitura de 0,5 segundo e log sanitizado.

## Reprodução

Com o ambiente de desenvolvimento em execução:

```powershell
docker compose --env-file .env.docker build app
docker compose --env-file .env.docker up -d --wait redis-cache
python scripts/benchmark-docker.py
```

O script cria um **novo** banco `biblioteca_benchmark_<timestamp>`, migra e gera 5.000 títulos, 15.000 exemplares, 200 leitores fictícios e 20.000 empréstimos encerrados de 2023–2024. Não apaga nem substitui bases existentes. O banco fica disponível para inspeção posterior. Dois containers temporários usam a **mesma imagem** e esse mesmo banco, primeiro com cache desativado e depois ativado. A porta local 8090 precisa estar livre. Os containers temporários são parados/removidos pelo próprio script; volumes e bancos não são removidos.

Cada cenário aquece quatro URLs e mede 200 requisições com concorrência 4. Metade dos casos inclui busca/filtro; há uma página posterior. O Node aplica timeout de 10 segundos, registra respostas inválidas como erro e limita o ensaio ao loopback. `BENCHMARK_REQUESTS` (até 5.000) e `BENCHMARK_CONCURRENCY` (até 20) permitem variar o cenário. O relatório contém p50, p95, erros, duração, throughput, amostras de CPU/memória antes/depois, recursos Docker, digest da imagem e `EXPLAIN` das consultas.

O servidor desse ensaio é o servidor de **desenvolvimento** do PHP, com processamento serial. As métricas descrevem esse workload local; não estimam usuários simultâneos suportados por uma implantação de produção. O script fixa, após medir o baseline e antes de executar o segundo cenário, uma meta exploratória de redução de 20% do p95, mantendo zero erros. Um resultado que não atinja a meta deve ser registrado, sem ajustar os números.

## Evidências iniciais de 25/09/2026

Host Intel i7-7700HQ, 4 núcleos/8 threads. Docker Desktop com 8 CPUs e 8.258.809.856 bytes de memória. PHP 8.4 em container e MySQL 8.4. Medição inicial antes do índice de ordenação:

| Cenário | p50 | p95 | Erros | Requisições/s |
| --- | ---: | ---: | ---: | ---: |
| Cache desativado | 212,83 ms | 300,62 ms | 0/200 | 17,86 |
| Cache ativado e aquecido | 139,16 ms | 240,41 ms | 0/200 | 26,21 |

A consulta isolada com busca realizou 5 acessos SQL sem cache e 1 com cache aquecido; o tempo SQL observado foi 19,20 ms e 1,07 ms, respectivamente. São amostras de diagnóstico, não percentis. O cache frio também faz consultas ao banco e não deve ser apresentado como ganho garantido.

Evidência local ignorada pelo Git: `output/testing/benchmark-20260925190758793418.json`. O `EXPLAIN` anterior usava varredura `ALL` e `using_filesort=true` em 5.000 títulos. A migration `000009` adicionou `livros_public_order_idx(status, titulo, id)`; no mesmo banco, o plano passou a acesso `ref` com `using_filesort=false`. A busca por substring continua sem promessa de índice textual. Não foi adicionada uma ferramenta de busca externa.

O índice foi ensaiado em uma restauração separada antes de aplicar ao banco de demonstração. Backup prévio: `__FILES/bd/biblioteca_biblioteca_dev_local_20260925-190939-039952.sql.zip`, checksum verificado. O plano posterior está em `output/testing/f6-index-plan.json`. O custo estimado caiu de 500,25 para 270,45; custo do otimizador não é duração em milissegundos nem volume real de linhas lidas.

Os testes de cache cobrem acerto, commit/rollback, alteração de autoria/categoria/status/disponibilidade, separação de filtros/páginas/hosts e fallback. Consultas completas, build e ensaio HTTP final constam no registro de execução.

## Medição final da F6

Após integrar índice, logs e métricas operacionais, uma nova execução com o mesmo tamanho de dataset e workload registrou:

| Cenário | p50 | p95 | Erros | Requisições/s |
| --- | ---: | ---: | ---: | ---: |
| Cache desativado | 272,40 ms | 352,33 ms | 0/200 | 14,23 |
| Cache aquecido | 232,13 ms | 300,44 ms | 0/200 | 16,89 |

O ganho de p95 foi **14,73%**. A meta exploratória de 20% (p95 até 281,86 ms) **não foi atingida nessa execução**. O resultado não é apresentado como aprovação de capacidade de produção. A telemetria passou a executar agregação em banco após cada resposta; no servidor de desenvolvimento serial esse trabalho também afeta a requisição seguinte. Essa é uma hipótese para investigar em outro perfil de servidor, não uma causalidade isolada por este experimento. Não repetir rodadas até obter um número conveniente.

A evidência sanitizada está versionada em [benchmark-f6.json](evidencias/benchmark-f6.json); o relatório completo local está em `output/testing/benchmark-20260925192555361835.json`. As amostras de recursos antes/depois não medem o pico durante a carga. O cache aquecido continua reduzindo a consulta pública a uma leitura SQL de revisão; não elimina o trabalho de sessão e telemetria do request completo.

No ensaio de falha, parar o serviço de cache também remove seu registro do DNS Docker. O primeiro fallback levou cerca de 6,7 segundos. `dns_opt` com `timeout:1` e `attempts:1` reduziu uma nova amostra a **2.414 ms**, com HTTP 200. Os 0,5 segundos do cliente Redis não limitam sozinhos a resolução DNS. A operação de fila permaneceu disponível e a página de saúde indicou `catalog_cache_unavailable`; após reiniciar o cache, voltou a HTTP 200 sem alertas.

O probe de relatórios, no banco com 20.000 empréstimos, encontrou acesso indexado por livro nas subconsultas de demanda e índices únicos para ocupação de exemplares. A primeira página de demanda levou aproximadamente 131–140 ms e a de indisponíveis 89–97 ms. A fila estava vazia nesse dataset e não permite conclusão de capacidade sobre reservas. Não foi adicionado um índice de relatório sem ganho demonstrado. `scripts/report-query-probe.php` reproduz o diagnóstico no banco exclusivo, sem mudar dados.
