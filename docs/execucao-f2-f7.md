# Execução sequencial F2–F7

Início: 25/09/2026. F0/F1 preservadas. Branch local: `codex/evolucao-biblioteca`.

## Decisões aprovadas pelo usuário

- Biblioteca única; cadastro público cria leitor; gestão exige funcionário.
- Prazo inicial: 14 dias corridos. Limite: 3 empréstimos abertos por leitor.
- Uma renovação de 14 dias; sem renovação quando houver reserva elegível esperando.
- Reservas em ordem de chegada, desempate por ID, retirada em 48 horas.
- Atraso bloqueia novas retiradas e renovações. Histórico pessoal continua acessível.
- Fuso: America/Sao_Paulo. Valores editáveis e política aplicada preservada em cada operação.
- Perda/baixa registrada pela equipe com motivo; encerramento por perda distinto de devolução física.
- Avisos no portal e por e-mail: 2 dias antes de vencer, 1 dia após atraso e quando reserva estiver disponível. Desenvolvimento envia somente ao Mailpit.
- Destino desta execução: demonstração local em Docker. Publicação em provedor externo não faz parte do aceite atual.

## Sequência e evidências

| Fase | Estado | Evidência / pendência |
| --- | --- | --- |
| F2 | Concluída e validada | Exemplares físicos, reconciliação auditável, circulação por unidade, perda e concorrência MySQL. |
| F3 | Concluída e validada | Política versionada, reservas FIFO, hold de 48 horas, renovação e bloqueio por atraso. |
| F4 | Concluída e validada | Outbox durável, Horizon, avisos, SMTP/Mailpit, falha terminal e reprocessamento administrativo. |
| F5 | Concluída e validada | API idempotente, token revogável, OpenAPI e sugestão ISBN com fallback manual. |
| F6 | Concluída e validada | Relatórios históricos, cache público com fallback, observabilidade e benchmark local delimitado. |
| F7 | Concluída e validada no escopo local | Demonstração Docker local, recuperação, rollback, backup, restauração lateral e roteiro de apresentação. |

Banco operacional preexistente e `.env` não serão usados. Mudanças de schema são ensaiadas em bancos descartáveis; antes de atualizar a demonstração Docker, gerar backup em `__FILES/bd/`. Dados da demonstração são fictícios; códigos atribuídos no ensaio representam somente essas unidades de teste.

## F2 — inventário físico e circulação

Para a demonstração, o catálogo está em [localhost:8088/catalogo](http://localhost:8088/catalogo), a gestão em [localhost:8088/livros](http://localhost:8088/livros) e as mensagens de desenvolvimento em [Mailpit](http://localhost:8028).

Um título novo inicia em `reconciliacao`: os contadores anteriores permanecem visíveis como referência, mas não autorizam novas retiradas. A equipe abre **Livros → Exemplares → Conferir inventário**, identifica cada unidade por código patrimonial único, informa a condição física e confirma a divergência quando a conferência alterar total ou disponibilidade.

A reconciliação grava exemplares físicos, vincula cada empréstimo aberto a uma unidade distinta em circulação e então altera o título para `exemplares`. O total e a disponibilidade passam a ser derivados das unidades existentes; editar `quantidade_total` não altera o acervo depois desse corte. Empréstimos devolvidos antes da reconciliação permanecem sem exemplar associado: o sistema não inventa cópias para o histórico.

Retiradas selecionam somente unidade identificada, em circulação e sem empréstimo ativo. A devolução encerra a locação e devolve a unidade à disponibilidade derivada. Em perda, a locação é encerrada com motivo `perda`, sem data de devolução, e o exemplar é marcado como extraviado. Alterar a condição de unidade emprestada é recusado.

### Concorrência e recuperação

A ordem usada nas operações críticas é **leitor → livro → empréstimo**, sempre relendo registros após os locks. Isso evita decidir a partir de objetos anteriores ao lock e reduz a janela de snapshots MVCC desatualizados. A alteração de condição segue o bloqueio do livro antes do exemplar, compatível com a retirada. A restrição única de `active_exemplar_id` impede que duas locações abertas persistam para a mesma unidade.

O ensaio MySQL executou duas conexões independentes concorrendo pela última cópia e duas devoluções concorrentes da mesma locação. A primeira disputa resultou em uma retirada e uma rejeição; a segunda em uma alteração e uma operação idempotente. A validação não equivale a teste de carga.

Foi gerado backup do banco de demonstração em `__FILES/bd/biblioteca_biblioteca_dev_local_20260925-172022-094067.sql.zip`. A restauração foi comprovada em banco novo `biblioteca_restore_20260925172827844028`; o banco operacional preexistente não foi aberto, migrado nem restaurado. Também foram revertidas duas migrations até antes do corte de inventário e reaplicadas no banco de ensaio.

## F3 — reservas, renovação e política de circulação

A política inicial v1 usa 14 dias por retirada, máximo de 3 empréstimos abertos, uma renovação de 14 dias, reserva por ordem de criação/ID, janela de retirada de 48 horas, bloqueio por atraso e fuso `America/Sao_Paulo`. Cada retirada, reserva e renovação guarda o snapshot da política aplicada; editar a política cria uma nova versão, sem reescrever o histórico anterior. A edição administrativa foi validada com uma string HTML como motivo: foi armazenada como texto e gerou v2 com os mesmos parâmetros. A aba de versão obsoleta é bloqueada para alteração.

O leitor pode reservar título indisponível. Quando uma cópia é liberada, a primeira reserva elegível recebe o hold e a cópia fica indisponível para qualquer outra retirada. Somente reserva ainda em `aguardando` bloqueia renovação; uma reserva já separada para outra cópia não bloqueia a renovação do empréstimo atual. O agendador expira holds fora da janela de 48 horas e reconcilia os títulos ativos para recompor saldos derivados.

No ensaio do navegador, o leitor A recebeu um hold de 48 horas e o leitor B ficou aguardando. A equipe registrou a retirada do hold de A, mantendo saldo zero; a renovação de A foi bloqueada enquanto B aguardava. Após o cancelamento de B, A renovou de 09/10 para 23/10, com histórico e limite de uma renovação preservados. A política inicial bloqueia atraso; ao desabilitar esse parâmetro, a retirada só é permitida se a data renovada ainda estiver futura.

As seis corridas MySQL independentes cobrem disputa pela última cópia, devolução repetida, duas retiradas concorrentes de títulos diferentes para o mesmo leitor com duas locações abertas, duas renovações da mesma locação, checkout concorrendo com reserva e duas reservas alocadas em FIFO. Cada filho retorna somente resultado de domínio; exceção inesperada encerra o ensaio.

O backup F3 está em `__FILES/bd/biblioteca_biblioteca_dev_local_20260925-180051-638062.sql.zip`. O backup F2 `biblioteca_biblioteca_dev_local_20260925-174706-975374.sql.zip` foi restaurado em `biblioteca_restore_20260925174835374688`, onde F3 e a demonstração foram aplicadas. O banco operacional preexistente permaneceu intocado.

## F4 — comunicações duráveis e operação

A migration F4 `2026_09_25_000006_create_durable_communications` foi ensaiada primeiro na restauração do backup F3. O primeiro ensaio revelou que o nome de um índice excedia o limite do MySQL; ele foi reduzido para `outbox_delivery_due_idx`. A restauração foi então aprovada no banco novo `biblioteca_restore_20260925181715629435`, a partir do backup `biblioteca_biblioteca_dev_local_20260925-180051-638062.sql.zip`; depois disso, a migration foi aplicada no banco de demonstração. O banco operacional preexistente não foi usado.

O ensaio real começou com o worker desligado: três intenções foram persistidas em `pending`, sem tentativas nem avisos no portal. A publicação colocou os três jobs no Redis, ainda pendentes enquanto o Horizon permanecia parado. Ao iniciar o worker, os três eventos passaram para `sent`, criaram três avisos e elevaram as mensagens no Mailpit de duas para cinco. Reexecutar um job já concluído não criou nova mensagem.

Com o Mailpit desligado, um quarto evento chegou a `failed` após três tentativas; o portal continuou com três avisos porque a transação de envio SMTP falhou. Depois de reativar o Mailpit, o administrador reprocessou o evento por POST na tela operacional: ele terminou em `sent`, criou o quarto aviso e enviou uma mensagem para a nova caixa do Mailpit. Reiniciar o Mailpit limpa a memória da caixa, portanto essa última observação começou com uma caixa vazia.

O scheduler real executou `reservas:expirar`, `comunicacoes:preparar` e `outbox:publicar` às 15:23, com término registrado nos logs. No navegador, o e-mail HTML e o portal com marcação de aviso como lido foram confirmados; leitor recebeu 403 na métrica do Horizon e administrador ativo recebeu 200 no dashboard, sem erro de console. A captura móvel final `output/playwright/f4-portal-mobile.png` foi revisada a 390 px, sem overflow; os cards de reservas F3 permanecem legíveis nesse viewport.

Validações automatizadas: suíte SQLite completa com 86 testes e 396 assertions; suíte MySQL com 94 testes e 447 assertions, registrada em `output/testing/f4-mysql.log`. O foco `CommunicationOutboxTest` teve 11 testes e 54 assertions, incluindo indisponibilidade do broker e republicação; o conjunto F4 teve 14 testes e 65 assertions, e `HorizonAuthorizationTest` acrescentou 6 testes e 17 assertions. Composer analyse, Pint, checagem de assets, build e `git diff --check` passaram.

## F5 — API e consulta bibliográfica

O ensaio real da API confirmou reserva com 201 e replay idempotente sem segundo efeito; a mesma chave com payload diferente respondeu 409. Um leitor diferente recebeu 404 para o recurso alheio. Renovação e cancelamento também responderam 200 em suas repetições sem duplicar a operação. Sessão por cookie sem token recebeu 401, assim como token revogado; o endpoint de consulta de token não expõe segredo e responde com `no-store`. Um cliente Node autenticou com um PAT temporário, que foi revogado após o ensaio.

A consulta ISBN real retornou 200 para uma edição `OL7353617M`, ano 1988, ISBN `9780140328721` e título *Fantastic Mr. Fox*. Uma tentativa posterior teve timeout SSL; o formulário manual continuou disponível. O sucesso de interface subsequente foi validado por replay da resposta real em cache, identificado como tal, sem alegar nova chamada externa bem-sucedida. Confirmar a sugestão preencheu o formulário e não criou livro: a contagem permaneceu em cinco antes e depois. Em viewport móvel não houve overflow nem erro de console.

O gate F5 foi concluído com 119 testes e 732 assertions em SQLite. O foco MySQL da API teve 30 testes e 306 assertions, incluindo três corridas independentes com 20 assertions; o reteste MySQL de token e redefinição de senha teve 11 testes e 63 assertions. A suíte MySQL completa passou com 130 testes e 803 assertions em 98,262 s, registrada em `output/testing/f5-mysql-full.log`. PHPStan, Pint, checagem de assets e build da imagem passaram. F6 iniciou após esse gate.

## F6 — relatórios, cache e observabilidade

Os relatórios separam fluxo no período de posição no fim da data de referência em `America/Sao_Paulo`. O navegador confirmou o filtro por título, a população `atrasados`, o prazo histórico e a reconciliação de dois empréstimos iniciados, dois abertos, um atrasado e somente o empréstimo operacional esperado no detalhamento. O CSV preserva a mesma consulta, população e filtros, inclui metadados e `no-store`; uma conta leitora recebeu 403 ao tentar exportá-lo. As três telas de relatórios foram verificadas a 390 px, com 200 e sem overflow ou erro de console.

O cache de catálogo usa Redis separado da fila e volta à consulta no banco se esse Redis ficar indisponível. No ensaio real, a queda induzida manteve o catálogo em 200, deixou a saúde operacional em 503 com `catalog_cache_unavailable` e preservou o Redis de fila em PONG; após a recuperação, a saúde retornou 200. A primeira interrupção por DNS levou 6,7 s; a configuração do Compose foi ajustada para uma tentativa e timeout de DNS de um segundo, reduzindo o ensaio seguinte para 2,414 s. O timeout de conexão de 0,5 s do cache não limita a resolução DNS.

Uma correlação atravessou request HTTP (81 ms), outbox, job de comunicação (230 ms, uma tentativa e `sent`), aviso no portal e Mailpit. Logs e métricas usam campos estruturados sem query, corpo, token ou dado de leitor. A consulta de diagnóstico de relatórios ficou entre 88 e 140 ms com 20 mil empréstimos sintéticos; a fila vazia do ensaio não sustenta alegação de escala da fila.

Validações automatizadas: SQLite completo com 141 testes e 887 assertions em 18,748 s (`output/testing/f6-sqlite-full.log`) e MySQL completo com 152 testes e 958 assertions em 151,564 s (`output/testing/f6-mysql-full.log`). PHPStan não reportou erros, Pint aplicou a ordenação de imports em logging, checagem de assets, build e `git diff --check` passaram.

## F7 — demonstração local, recuperação e apresentação

A F7 foi concluída no projeto Docker separado `biblioteca-demo`: sete serviços isolados (app, MySQL, Redis de fila, Redis de catálogo, Horizon, scheduler e Mailpit), aplicação em `http://localhost:8089` e Mailpit em `http://localhost:8029`, ambos somente em loopback. A imagem C `b48a2325b4aa12faacb5` usa PHP 8.4 com Apache; o processo da aplicação é `www-data`, código não é gravável, storage é gravável e os Blade compilados são isolados por release. O projeto e os volumes não são os mesmos do Compose de desenvolvimento.

O seeder protegido foi exercitado com cenário inteiramente fictício: 5 usuários, 14 títulos, 30 exemplares, 5 locações e 3 reservas. As três intenções iniciais da outbox foram publicadas pelo fluxo assíncrono e chegaram ao portal e ao Mailpit. Senhas das contas pertencem somente ao seed e ao arquivo local ignorado; não são exibidas nos scripts ou na documentação.

No navegador, a renovação da locação 4 mudou o prazo de 9 para 23 de outubro e a segunda tentativa foi bloqueada. A equipe devolveu a locação 3; a primeira reserva FIFO ficou disponível por 48 horas, recebeu aviso no portal e gerou a quarta mensagem no Mailpit. Os três relatórios responderam 200 em 390 px sem overflow; leitor recebeu 403 para gestão, Horizon e operação, e administrador recebeu 200. O smoke final confirmou prazo de 23 de outubro e limite de uma renovação na locação 4, devolução da locação 3 em 25 de setembro, um empréstimo aberto no portal e ausência de `pageerrors`/overflow a 390 px. As mutações se mantiveram após seed replay, rollback, redeploy e restauração.

O workflow remoto foi preparado, mas não executado. O gate final registrou SQLite com **147 testes / 937 assertions**, sintaxe de **238 arquivos PHP** incluindo Blade, Pint, PHPStan, assets, build, auditorias, `git diff --check` e os **13** checks Python da automação. A suíte MySQL final registrou **158 testes descobertos / 958 assertions**, seis skips exclusivos do `DemoSeeder` em SQLite, zero falhas e 158,686 s (`output/testing/f7-mysql-full.log`). Uma falha induzida no canário após a troca real da versão B recuperou automaticamente a versão A como `last_known_good`; os canários HTTP e de saúde passaram e o journal foi limpo em 160,47 s. O rollback real de B para A levou 78,86 s, preservou as contagens 5 usuários/14 títulos/30 exemplares/5 locações/3 reservas e ficou `healthy`; a republicação de B levou 80,78 s e também ficou saudável.

O deploy C foi aprovado saudável. O backup local `__FILES/bd/biblioteca_demo_20260925201843917624.sql.zip` (10.561 bytes) teve checksum verificado e reiniciou os writers. A restauração lateral em `biblioteca_demo_restore_20260925201903352063` levou 10,93 s, teve storage isolado e HTTP autenticado, preservou as contagens 5/14/30/5/3 e `invalid_open_loans=0`. Os hashes dos campos de circulação, renovação e reservas permaneceram iguais antes e depois de deploy, backup e restauração. A evidência versionada é `docs/evidencias/demo-f7.json`. A revisão independente F7 não encontrou achados P0 ou P1.

Esta conclusão vale para a demonstração local. Não sustenta alegação de publicação pública, nuvem, TLS externo, entrega SMTP pela internet, reset destrutivo, escala ou SLA. Mailpit valida somente SMTP local; ISBN ainda depende de rede externa e pode sofrer timeout. O ganho de p95 do benchmark foi 14,73% (352,33 ms para 300,44 ms), abaixo da meta exploratória de 20% (281,86 ms), sem provar capacidade de escala.
