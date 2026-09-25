# Desenvolvimento e entregas F0–F7

## Ambiente isolado

Pré-requisitos: Docker Desktop com engine Linux/WSL2 ativo e Node 24. O runtime da aplicação é PHP 8.4 (mínimo 8.4.1), Laravel 12, MySQL 8.4, Redis 7.4 e Mailpit. As imagens de infraestrutura e do PHP estão fixadas por digest. O Compose é para desenvolvimento: usa `artisan serve` e inclui dependências de teste.

Na raiz do checkout, em PowerShell ou shell do WSL:

```powershell
node scripts/init-docker.mjs
docker compose --env-file .env.docker up -d --build --wait app
docker compose --env-file .env.docker exec app php artisan migrate
docker compose --env-file .env.docker up -d --wait worker scheduler
```

O inicializador gera segredos aleatórios em `.env.docker` ignorado pelo Git. Ele preserva um arquivo já existente. O Compose não carrega o `.env` local da aplicação nem monta o diretório do projeto; usa uma imagem com snapshot do código. Depois de alterar código, execute novamente `up -d --build`. A primeira inicialização cria volumes novos, banco `biblioteca_dev` e banco `biblioteca_testing` com usuário próprio, sem permissões cruzadas. Migrações são um passo explícito: inicie a aplicação, aplique o schema e só então inicie worker e scheduler, para que processos recorrentes não consultem tabelas ainda inexistentes.

- Aplicação: <http://localhost:8088>.
- Caixa de e-mail local: <http://localhost:8028>.
- MySQL, Redis e SMTP não possuem portas publicadas no host.
- Aplicação e caixa de e-mail escutam somente em loopback.

`DOCKER_APP_PORT` e `DOCKER_MAIL_PORT` permitem ajustar as portas. Não altere senhas de `.env.docker` após criar o volume MySQL sem realizar a correspondente rotação no banco. Não apague volumes para contornar falha de acesso. `docker compose --env-file .env.docker stop` para os serviços sem remover dados.

### Permissão do script de inicialização MySQL

`docker/mysql/init-testing.sh` deve permanecer executável no Git (`100755`). A imagem MySQL executa scripts `.sh` com esse bit em outro processo; arquivos não executáveis são carregados com `source`. Nesse segundo caso, `set -u` do script afeta o entrypoint e encerra a primeira inicialização ao consultar a variável opcional `MYSQL_ONETIME_PASSWORD`.

O primeiro CI Linux reproduziu essa diferença, que o bind mount do Docker Desktop no Windows mascarava. O ensaio de regressão em container novo, sem portas ou volumes existentes, confirmou falha com `0644` e sucesso com `0755`, incluindo criação de `biblioteca_testing` e autenticação de `biblioteca_test`. Preserve o modo ao alterar ou transportar o script; não contorne a falha apagando volumes existentes.

## Primeiro administrador e usuários existentes

Cadastre uma conta pelo formulário público. Ela terá acesso imediato como leitor. Para provisionar o administrador, execute:

```powershell
docker compose --env-file .env.docker exec app php artisan biblioteca:bootstrap-admin
```

O comando pede ID ou e-mail de uma conta existente, promove a conta e registra auditoria. Não cria senha padrão. Requer acesso administrativo ao terminal do ambiente. O administrador acessa **Equipe e acessos** para promover contas; bibliotecários cadastram leitores com e-mail em **Leitores**.

Em banco preexistente, a migration atribui `leitor` ativo a todas as contas: não infere funcionários nem concede administração por heurística. Antes de migrar esse banco, faça backup, ensaie em uma cópia e identifique explicitamente quem será administrador. O banco local preexistente não foi migrado nesta entrega.

## Matriz de acesso

| Superfície | Visitante | Leitor ativo | Bibliotecário ativo | Administrador ativo |
| --- | --- | --- | --- | --- |
| Home e catálogo de títulos ativos | Sim | Sim | Sim | Sim |
| Próprios empréstimos e histórico | Login | Sim | Sim | Sim |
| Empréstimo alheio no portal | Não | Não | Não | Não |
| Acervo completo, autores, categorias, circulação | Não | Não | Sim | Sim |
| Cadastro/edição/inativação de leitores | Não | Não | Sim | Sim |
| Alteração de papel e acesso da equipe | Não | Não | Não | Sim |
| Comunicações, reprocessamento e painel Horizon | Não | Não | Não | Sim |

Cadastro público é automático, sem aprovação nem verificação obrigatória de e-mail nesta etapa. O portal consulta dados próprios; retirada e devolução são registradas pela equipe. Inativação bloqueia login, rotas protegidas e novos empréstimos, preservando o histórico. O sistema impede alterar o próprio papel/acesso e mantém ao menos um administrador ativo. Papéis e status enviados em payload público não concedem privilégios. Rotas de leitores não editam membros da equipe.

Recuperação de senha usa token com expiração, limitação de requisições e resposta genérica para conta inexistente/inativa. No Docker, as mensagens chegam somente ao Mailpit. Cadastro de leitor pela equipe não envia convite: sem senha inicial, a pessoa usa **Esqueci minha senha**. Confirmação de e-mail, convites e políticas de retenção/anonimização continuam para definição específica.

Auditoria persiste criação/inativação de leitores e alterações de papel/acesso, com ator, alvo e valores de permissão, sem senha ou e-mail. Logs de requisição registram ID de correlação, nome de rota, status e duração sem corpo, query string, token ou credenciais. Auditoria na aplicação não equivale a imutabilidade contra administradores do banco.

## Inventário físico e circulação (F2)

Links locais da demonstração: [catálogo](http://localhost:8088/catalogo), [gestão de livros](http://localhost:8088/livros) e [Mailpit](http://localhost:8028). A gestão exige autenticação de bibliotecário ou administrador.

O menu de gestão oferece **Livros → Exemplares**. Um livro criado agora permanece em reconciliação até a equipe conferir as unidades: essa tela exige um código patrimonial único, confirmação de identificação física e uma condição para cada exemplar. Enquanto o título estiver nesse estado, a equipe pode registrar devoluções antigas, mas não novas retiradas.

Ao concluir a reconciliação, cada empréstimo aberto deve apontar para uma unidade diferente em circulação. A tela pede confirmação e registro do motivo quando a contagem física divergir do saldo anterior. Unidades em manutenção, extraviadas ou baixadas não podem representar um empréstimo aberto. Depois do corte, `quantidade_total` e `quantidade_disponivel` são derivados dos exemplares; a edição manual do total é recusada.

O histórico fechado anterior ao inventário preserva `exemplar_id` nulo. Uma devolução encerra a locação com data de devolução; uma perda é registrada pela equipe com motivo, encerra a locação sem data de devolução e marca a unidade como extraviada. Códigos patrimoniais são únicos no acervo e uma unidade não pode ter mais de uma locação ativa.

As operações de retirada, devolução, perda e ajuste de condição usam transações. Nos caminhos concorrentes, a aplicação bloqueia e relê em ordem **leitor → livro → empréstimo**; o contador é sincronizado depois da mudança com base nos exemplares físicos. A validação de concorrência descrita abaixo usa clientes MySQL independentes, porque SQLite e testes sequenciais não reproduzem locks e snapshots MVCC do banco de produção.

## Reservas, renovação e políticas (F3)

O leitor ativo pode reservar título sem exemplar disponível. A fila usa criação e ID como desempate; quando uma unidade é liberada, a primeira reserva elegível recebe o hold por 48 horas. Uma cópia com hold não pode ser retirada por outro leitor. O leitor titular conclui a retirada pela equipe; a reserva fica atendida e o vínculo físico passa para a locação.

A política v1 aplica 14 dias por empréstimo, até 3 abertos, uma renovação de 14 dias, atraso bloqueando retirada/renovação e fuso `America/Sao_Paulo`. Retiradas, reservas e renovações mantêm o snapshot aplicado. Alterar parâmetros pela administração cria versão nova, exige motivo e não altera operações anteriores; telas de versões não atuais não aceitam edição.

Uma reserva em `aguardando` para o título bloqueia renovação. Uma reserva já disponível para retirada, vinculada a outra cópia, não bloqueia renovação do empréstimo existente. Após cancelamento ou expiração da fila pendente, uma renovação autorizada acrescenta 14 dias e preserva histórico e limite. Quando a política desabilita bloqueio por atraso, ainda é exigida data de vencimento futura para permitir retirada.

O agendador expira holds de 48 horas e reconcilia títulos ativos. Ele mantém a reserva encerrada no histórico e recalcula disponibilidade a partir de empréstimos e holds ainda ativos.

## Comunicações, fila e operação (F4)

O scheduler prepara lembretes de vencimento, atraso e reserva disponível com `comunicacoes:preparar` e publica a outbox durável com `outbox:publicar`, ambos a cada minuto. A preparação não envia diretamente: a intenção persistida entra na fila Redis `communications`, que é processada pelo container `worker` com Horizon. Para diagnóstico ou recuperação local, os comandos podem ser executados manualmente depois de a aplicação, migration, worker e scheduler estarem ativos:

```powershell
docker compose --env-file .env.docker exec app php artisan comunicacoes:preparar
docker compose --env-file .env.docker exec app php artisan outbox:publicar --limit=100
docker compose --env-file .env.docker exec worker php artisan horizon:status
```

O job de comunicação tem até três tentativas, timeout de 20 segundos e espera de 5, 30 e 120 segundos entre tentativas. O supervisor Horizon limita cada execução a 30 segundos; o Redis só disponibiliza uma tentativa novamente após 90 segundos. Essa ordem evita que uma cópia da mesma execução seja liberada enquanto o worker ainda deveria estar ativo. O supervisor atende `communications` e `default`; o Horizon usa prefixo Redis próprio do ambiente.

O painel [Horizon](http://localhost:8088/horizon) e a tela **Operação → Comunicações** exigem administrador ativo. Leitores e bibliotecários não acessam telemetria, eventos nem o reprocessamento. Os eventos passam por `pending`, `processing`, `sent`, `cancelled` ou `failed`; somente falhas terminais podem ser reenfileiradas pelo administrador, após corrigir a causa. O reprocessamento retorna o evento a `pending`, zera tentativas e registra auditoria antes de publicá-lo novamente.

O portal mantém um aviso interno idempotente por evento. SMTP/Mailpit, porém, não fornece entrega exatamente uma vez: um provedor pode aceitar a mensagem antes de a aplicação gravar o sucesso, e uma interrupção nesse intervalo permite duplicidade em nova tentativa. Em um provedor externo, use uma chave de idempotência suportada por ele; não trate o status local como confirmação de leitura ou entrega ao destinatário.

## Qualidade e testes

Com PHP 8.4 e Composer locais:

```powershell
composer install --no-scripts
php artisan package:discover
npm ci --ignore-scripts
composer lint
composer analyse
composer test
npm run assets:check
npm run build
composer audit
npm audit --audit-level=high
```

`composer setup` instala dependências e confere assets; não gera chave nem executa migrations. A análise estática usa Larastan/PHPStan nível 5, sem baseline de erros ignorados. Pint verifica estilo. A integridade dos arquivos publicados de Tom Select e Flatpickr é comparada byte a byte com as versões do lockfile. O build Vite é verificado, embora as telas Blade usem diretamente os arquivos locais em `public/`.

Testes rápidos usam exclusivamente SQLite em memória. O bootstrap rejeita configuração cacheada e neutraliza URLs alternativas de conexão/e-mail; a classe base recusa outro banco antes de executar `RefreshDatabase`. Use `composer test`/`vendor/bin/phpunit`, que aplicam o bootstrap antes da inicialização da aplicação. Integração usa banco exclusivo e descartável:

```powershell
docker compose --env-file .env.docker run --rm tests
```

Esse comando executa a mesma suíte em MySQL, além de rollback transacional e conectividade Redis com TTL. Não roda contra `biblioteca_dev`. O bootstrap exige variáveis exclusivas `BIBLIOTECA_TEST_DB_HOST`, `BIBLIOTECA_TEST_DB_PASSWORD` e `BIBLIOTECA_TEST_REDIS_HOST`, fornecidas pelo Compose/CI. Só aceita os hosts locais previstos, força usuário/banco de teste e neutraliza URLs alternativas. Redis usa DB 15 e prefixo `biblioteca_testing:`, sem herdar credenciais operacionais. A execução sem configuração explícita é bloqueada antes de inicializar Laravel.

Redis sustenta a fila de comunicações da F4 e o Horizon. O catálogo público usa Redis LRU separado, com invalidação após commit e fallback ao MySQL; sessões, limitação e métricas operacionais ficam no cache persistente em banco. A concorrência de circulação foi validada no MySQL com processos independentes; isso cobre a disputa pela última cópia e devoluções concorrentes, sem substituir teste de carga. A mensageria F4 foi exercitada até o efeito final no portal e Mailpit, inclusive com worker parado, falha do Mailpit, falha terminal e reprocessamento administrativo.

A F5 acrescenta API autenticada por token e consulta de ISBN como sugestão para o cadastro manual. No ensaio local, reserva teve 201 e replay idempotente com um único efeito; payload diferente sob a mesma chave recebeu 409, recurso de outro leitor recebeu 404 e token ausente ou revogado recebeu 401. Renovação e cancelamento também foram repetidos sem duplicar efeito. A consulta ISBN teve uma resposta real de edição confirmada e uma tentativa posterior com timeout SSL; o formulário manual permaneceu disponível. A confirmação de sugestão usou replay identificado da resposta real em cache, preencheu o formulário e não escreveu um livro. O gate F5 foi concluído com 119 testes e 732 assertions em SQLite; o foco MySQL da API teve 30 testes e 306 assertions, incluindo três corridas independentes com 20 assertions, e o reteste MySQL de token e redefinição de senha teve 11 testes e 63 assertions. A suíte MySQL completa passou com 130 testes e 803 assertions em 98,262 s, registrada em `output/testing/f5-mysql-full.log`. PHPStan, Pint, checagem de assets e build da imagem passaram.

A F6 acrescenta relatórios históricos de circulação, fila atual e inventário indisponível, com exportação CSV equivalente à consulta da tela, metadados, `no-store` e proteção contra fórmulas. Os fluxos contam eventos do período; abertos e atrasados recompõem o estado ao fim de uma data de referência, usando a primeira renovação posterior para recuperar o prazo histórico. O cache do catálogo usa Redis LRU separado da fila, invalidação posterior ao commit e fallback ao banco. A observabilidade agrega métricas expiráveis, registra request, outbox e job por correlação e expõe saúde administrativa sem DSN, payload ou dado de leitor. O benchmark usa banco sintético isolado e documenta a limitação do servidor PHP local; a fila vazia do ensaio não é uma alegação de escala.

O workflow `.github/workflows/quality.yml` executa instalação pelos lockfiles, auditorias, sintaxe, Pint, Larastan, assets, build e suítes SQLite/MySQL/Redis em push e pull request. Um segundo job constrói a imagem, inicia os serviços, aplica migrations no banco novo do runner e confere `/up` e `/catalogo`. O workflow só terá uma execução remota após publicação no GitHub. Não há deploy configurado nesta etapa.

A F7 entrega uma demonstração local separada em `compose.demo.yaml`. O projeto `biblioteca-demo` contém sete serviços (app, MySQL, Redis de fila, Redis de catálogo, Horizon, scheduler e Mailpit), expõe aplicação em `http://localhost:8089` e Mailpit em `http://localhost:8029`, somente em loopback, e não reutiliza os volumes de desenvolvimento. A imagem usa PHP 8.4 com Apache e roda como `www-data`, mantém código não gravável, storage gravável e isola os Blade compilados por release. O cenário é carregado exclusivamente pelo seeder protegido: fora de testes SQLite em memória, ele exige MySQL, `APP_ENV=production`, banco `biblioteca_demo`, `DEMO_ENABLED=true`, senhas fornecidas pelo ambiente e banco de domínio vazio. Veja [demonstração](demonstracao.md) e [operação local](deploy-local.md). Deploy C, backup local verificado e restauração lateral autenticada concluíram o gate F7 local.

## Evidências da execução em 25/09/2026

- PHP 8.4.20 no host e 8.4.26 no container; Laravel 12.69.2 pelos mesmos lockfiles.
- PHPUnit SQLite: **86 testes / 396 assertions**. PHPUnit MySQL + Redis: **94 testes / 447 assertions**, registrado em `output/testing/f4-mysql.log`. As seis corridas MySQL independentes: **6 testes / 40 assertions**, cobrindo F2 e F3. O foco `CommunicationOutboxTest` passou com **11 testes / 54 assertions**, o conjunto F4 com **14 testes / 65 assertions** e `HorizonAuthorizationTest` com **6 testes / 17 assertions**.
- Pint, PHPStan nível 5, sintaxe de **94 arquivos PHP**, validação Composer, integridade dos assets, build Vite e `git diff --check` aprovados.
- Auditorias de dependências Composer/npm sem vulnerabilidades reportadas na consulta desta data; não é garantia de ausência de futuras vulnerabilidades.
- Docker construído sem `vendor` do host, iniciado com volumes novos; aplicação, MySQL, Redis e Mailpit saudáveis. Migrations aplicadas somente no banco Docker novo e nas bases descartáveis de teste. O backup F2 foi restaurado em `biblioteca_restore_20260925174835374688`, onde F3 e a demonstração foram aplicadas; o backup F3 atual é `__FILES/bd/biblioteca_biblioteca_dev_local_20260925-180051-638062.sql.zip`. Banco local preexistente e `.env` preservados.
- Navegador: cadastro público e portal vazio; gestão negada ao leitor; cadastro de leitores pela equipe; promoção pelo administrador; equipe sem acesso à administração; empréstimo registrado pela equipe e consultado pelo titular; outro leitor e funcionários bloqueados no detalhe pessoal alheio; inativação revogando sessão e negando login; recuperação por SMTP/Mailpit até redefinição e novo login.
- Navegador F2: divergência de inventário recusada e reconciliação concluída com três cópias (duas em circulação e uma em manutenção); retirada, perda sem data de devolução e bloqueio correspondente verificados. O vínculo de empréstimo legado na reconciliação foi coberto por teste automatizado, não pelo navegador.
- Navegador F3: hold de A por 48 horas, B aguardando, retirada atendendo A, saldo zero, bloqueio de renovação enquanto B aguardava, cancelamento de B e renovação de A de 09/10 para 23/10. A política v2 com mesmo parâmetro e motivo HTML foi gravada; a versão obsoleta permaneceu bloqueada para edição.
- F4: com worker desligado, três intenções ficaram pendentes sem avisos; depois da publicação e início do Horizon, ficaram enviadas, com três avisos e Mailpit de duas para cinco mensagens. Reexecutar job concluído não criou mensagem. Com Mailpit desligado, a quarta intenção terminou em falha após três tentativas e não criou aviso; após reativá-lo, o POST administrativo de reprocessamento a enviou, criou o quarto aviso e uma mensagem na nova caixa Mailpit. Scheduler real executou expiração de reservas, preparação e publicação da outbox às 15:23. E-mail HTML, aviso no portal marcado como lido, bloqueio 403 do leitor na métrica e dashboard Horizon 200 para administrador foram confirmados no navegador, sem erro de console.
- Mobile a 390 px: cards de reserva e renovação sem overflow, além de portal, inventário e formulário de leitor. A captura final `output/playwright/f4-portal-mobile.png` foi revisada; os cards de reservas F3 permanecem legíveis. A tabela conserva rolagem interna. Menu abre, fecha com Escape e devolve foco. Console sem erros inesperados; respostas 403 esperadas nos testes de permissão.
- F5: reserva API 201 com replay sem efeito adicional, chave idempotente divergente 409, recurso alheio 404, token ausente ou revogado 401 e renovação/cancelamento repetidos sem duplicação. Cliente Node autenticado com PAT temporário e depois revogado. A resposta ISBN real confirmou edição `OL7353617M`, ano 1988, ISBN `9780140328721` e *Fantastic Mr. Fox*; a consulta seguinte sofreu timeout SSL e o fallback manual permaneceu acessível. A tela de sucesso posterior reproduziu a resposta real em cache, identificada como replay; confirmar preencheu o formulário sem criar livro, mantendo cinco antes e depois. Mobile sem overflow e console sem erro. O gate final passou com 119 testes/732 assertions em SQLite, foco MySQL API 30/306 incluindo três corridas/20 assertions, reteste MySQL de token/redefinição 11/63 e suíte MySQL completa 130/803 em 98,262 s (`output/testing/f5-mysql-full.log`); PHPStan, Pint, assets e imagem passaram.
- F6: relatórios a 390 px responderam 200 sem overflow ou erro de console; o leitor recebeu 403 no CSV. Filtro por título, prazo histórico e população de atrasados reconciliaram dois empréstimos no período, dois abertos, um atrasado e somente o empréstimo esperado no detalhamento; o CSV preservou os metadados e `no-store`. Com Redis de catálogo interrompido, o catálogo permaneceu 200 pelo fallback ao banco, a saúde retornou 503 com `catalog_cache_unavailable` e a fila Redis continuou em PONG; após restaurar o cache, a saúde retornou 200. A interrupção DNS inicial durou 6,7 s; `dns_opt` com timeout de um segundo e uma tentativa reduziu o ensaio a 2,414 s, sem atribuir esse tempo ao timeout de conexão de 0,5 s. Uma correlação percorreu HTTP de 81 ms, outbox, job de 230 ms em uma tentativa `sent`, aviso do portal e Mailpit. O probe de relatórios com 20 mil empréstimos sintéticos ficou entre 88 e 140 ms; a fila vazia não é evidência de escala. Gate completo: SQLite **141 testes / 887 assertions** em 18,748 s (`output/testing/f6-sqlite-full.log`) e MySQL **152 testes / 958 assertions** em 151,564 s (`output/testing/f6-mysql-full.log`); PHPStan sem erros, Pint, assets, build e `git diff --check` aprovados.
- F7 concluída para escopo local: a imagem C `b48a2325b4aa12faacb5` foi implantada saudável nos sete serviços. A imagem PHP 8.4/Apache roda como `www-data`, mantém código não gravável, storage gravável e isola os Blade compilados por release. O seed fictício criou 5 usuários, 14 títulos, 30 exemplares, 5 locações e 3 reservas; as três intenções da outbox alcançaram portal e Mailpit pelo fluxo assíncrono. No navegador, a renovação da locação 4 passou de 9 para 23 de outubro e uma segunda renovação foi bloqueada; a equipe devolveu a locação 3, disponibilizando a primeira reserva FIFO por 48 horas, com aviso no portal e quarta mensagem no Mailpit. Os três relatórios responderam 200 a 390 px, sem overflow; leitor recebeu 403 em gestão, Horizon e operação, e administrador recebeu 200. O smoke final confirmou prazo de 23 de outubro e limite de uma renovação na locação 4, devolução da locação 3 em 25 de setembro, um empréstimo aberto no portal e ausência de `pageerrors`/overflow a 390 px, preservando mutações após seed replay, rollback, redeploy e restauração. Passaram SQLite **147 testes / 937 assertions**, sintaxe final de **238 arquivos PHP** incluindo Blade, Pint, PHPStan, assets, build, auditorias, `git diff --check` e os **13** checks Python da automação. A suíte MySQL final passou com **158 testes descobertos / 958 assertions**, seis skips exclusivos do `DemoSeeder` em SQLite, zero falhas e 158,686 s (`output/testing/f7-mysql-full.log`). Após falha induzida no canário depois da troca real da versão B, a automação recuperou A como `last_known_good`; os canários HTTP e saúde passaram, o journal foi limpo em 160,47 s. O rollback real de B para A levou 78,86 s, preservou as contagens 5/14/30/5/3 e retornou saúde `healthy`; a republicação de B levou 80,78 s e também ficou saudável. O backup local `__FILES/bd/biblioteca_demo_20260925201843917624.sql.zip` (10.561 bytes) teve checksum verificado e writers reiniciados. A restauração lateral em `biblioteca_demo_restore_20260925201903352063` levou 10,93 s, teve storage isolado e HTTP autenticado, preservou 5/14/30/5/3 e `invalid_open_loans=0`; hashes dos campos de circulação, renovação e reservas permaneceram iguais antes e depois de deploy/backup/restore. A evidência versionada é `docs/evidencias/demo-f7.json`.
- Revisão independente dos controles de ambiente e autorização; ajustes validados no isolamento dos testes e no throttle de cadastro.

Limites: CI remoto está preparado, mas ainda não executado; não houve commit, push ou deploy público. Não houve teste de carga, SLA ou SMTP externo. A recuperação F2–F6 foi ensaiada em banco novo e a concorrência com processos independentes foi validada no MySQL de teste. A F7 está concluída somente como demonstração local: Mailpit valida SMTP local, ISBN depende de rede externa e pode sofrer timeout, e o ganho de p95 no benchmark foi 14,73% (352,33 ms para 300,44 ms), abaixo da meta exploratória de 20% (281,86 ms), sem provar escala.

## Roteiro de demonstração

1. Como visitante, buscar e abrir um título ativo; título inativo não aparece no catálogo.
2. Cadastrar leitor e visualizar histórico vazio, sem menu de gestão. Tentar `/livros`: acesso negado.
3. Provisionar administrador; cadastrar outra conta e promovê-la a bibliotecário.
4. Como bibliotecário, cadastrar autor, categoria, livro e leitor. Abrir **Exemplares**, conferir unidades físicas e justificar qualquer divergência.
5. Registrar empréstimo para o leitor e consultar o prazo. Devolver ou registrar perda com motivo para observar os efeitos diferentes no histórico e no exemplar.
6. Como leitor, reservar título indisponível. Devolver uma cópia, conferir o hold de 48 horas e concluir a retirada pela equipe.
7. Criar uma segunda reserva e tentar renovar o empréstimo; cancelar a reserva pendente e renovar, confirmando o novo prazo e o histórico.
8. Como leitor, consultar o empréstimo e prazo; trocar o ID por empréstimo alheio deve negar acesso.
9. Solicitar recuperação de senha, abrir a mensagem no Mailpit, redefinir e entrar com a nova senha.
10. Inativar leitor; login/rotas protegidas e nova retirada devem ser negados, mantendo o histórico na gestão.

Use exclusivamente dados fictícios no ambiente de demonstração. As próximas entregas permanecem no [plano de evolução](plano-evolucao.md).
