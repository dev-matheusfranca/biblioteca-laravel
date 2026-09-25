# Decisões de arquitetura

Estas ADRs registram escolhas adotadas para a demonstração local da biblioteca. Elas descrevem o que foi deliberadamente mantido simples e os limites conhecidos; não são promessa de capacidade em ambiente de produção.

## ADR-001 — Monólito Laravel com Blade e Actions

**Status:** aceita.

**Contexto.** A aplicação reúne portal do leitor, gestão do acervo e regras de circulação em uma biblioteca única.

**Decisão.** Usar Laravel 12 como monólito, Blade para as telas, Form Requests para entrada, middleware/Gates/Policies para autorização e Actions para transações de domínio.

**Consequência.** A equipe mantém um único deploy e um único modelo transacional. Controllers permanecem finos e a autorização não depende de controles desabilitados no HTML. A solução não é separada em microsserviços nem preparada como multiempresa.

## ADR-002 — Exemplares físicos como fonte do saldo após reconciliação

**Status:** aceita.

**Contexto.** Quantidades agregadas não identificam qual unidade foi retirada, está em manutenção ou foi perdida.

**Decisão.** Todo título novo começa em reconciliação. A equipe cadastra exemplares identificados fisicamente e habilita circulação após conferir os saldos; então o saldo é derivado dessas unidades.

**Consequência.** Código patrimonial é único, condição é verificável e uma unidade não pode ter duas locações abertas. Edições de quantidade agregada depois do corte são recusadas. Locações fechadas anteriores preservam `exemplar_id` nulo em vez de receber cópias históricas inventadas.

## ADR-003 — Concorrência transacional e fila FIFO de reservas

**Status:** aceita.

**Contexto.** Retirada, devolução, perda, reserva e renovação podem alcançar o mesmo leitor, título ou exemplar ao mesmo tempo.

**Decisão.** Executar os fluxos mutáveis em transação, bloquear e reler na ordem leitor → título → locação. Ordenar reservas por criação e ID e alocar hold de retirada por 48 horas à primeira pessoa elegível.

**Consequência.** A ordem reduz deadlocks e a releitura evita decidir por snapshot obsoleto. Há testes com processos MySQL independentes para as corridas principais; testes SQLite continuam úteis para regra, mas não comprovam os locks do InnoDB.

## ADR-004 — Política de circulação versionada e congelada no uso

**Status:** aceita.

**Contexto.** Alterar prazos e limites não deve reescrever obrigações já iniciadas.

**Decisão.** Versionar a política e registrar o snapshot aplicado em cada operação de circulação. A versão inicial usa 14 dias, máximo de três locações abertas, uma renovação, bloqueio por atraso e fuso de São Paulo.

**Consequência.** A administração pode criar uma versão nova com motivo, sem modificar a regra aplicada a operações anteriores. Uma reserva aguardando pode bloquear a renovação; uma reserva já alocada em outra cópia não altera retroativamente uma locação existente.

## ADR-005 — Sessões no banco e revogação server-side

**Status:** aceita.

**Contexto.** O portal do leitor e a equipe exigem controle de acesso que sobreviva a múltiplas requisições e possa ser revogado.

**Decisão.** Persistir sessões no banco e revogar sessões e tokens pessoais quando a conta é inativada ou seu acesso é alterado.

**Consequência.** A aplicação verifica papel e atividade no servidor. Cadastro público cria somente leitor; bibliotecários cuidam de circulação e administradores cuidam da equipe e da operação. O navegador não é fonte de autorização.

## ADR-006 — Outbox durável, Redis/Horizon e limite do SMTP

**Status:** aceita.

**Contexto.** Enviar aviso dentro da transação de empréstimo deixa o fluxo dependente do e-mail; enviar apenas à fila pode perder a intenção se houver falha entre gravação e publicação.

**Decisão.** Gravar a intenção em outbox no MySQL, publicá-la em fila Redis `communications` e processá-la com Horizon. Avisos do portal e envios são idempotentes e falhas podem ser reprocessadas pela equipe administrativa.

**Consequência.** A intenção é durável e observável. Não existe garantia exatamente uma vez sobre SMTP: uma confirmação externa pode se perder na janela entre o provedor e a persistência local. Mailpit é somente a caixa local de demonstração; não há provedor externo comprovado.

## ADR-007 — Redis separado para fila e para catálogo

**Status:** aceita.

**Contexto.** Um cache LRU descartável não pode competir com a fila de comunicações, sessões ou controles operacionais.

**Decisão.** Usar conexão Redis dedicada para a fila e outra para o catálogo público; manter cache operacional persistente no banco.

**Consequência.** A perda do cache de catálogo provoca fallback ao MySQL e pode afetar latência, mas não perde circulação. Invalidação e TTL reduzem desatualização; não fornecem consistência distribuída absoluta. Métricas e limites não dependem do cache LRU.

## ADR-008 — Busca ISBN limitada com retorno manual garantido

**Status:** aceita.

**Contexto.** Uma integração bibliográfica deve ajudar o cadastro sem tornar a disponibilidade da Open Library condição para criar um livro.

**Decisão.** Consultar apenas o endpoint de busca da Open Library em host fixo, por ISBN validado. A aplicação seleciona uma edição, limita timeout, conexão, tamanho de resposta, repetição e ritmo; cacheia achados e ausências por períodos distintos.

**Consequência.** Título, autores, ano e ISBN podem preencher o formulário a partir de uma edição encontrada. Resultado ausente, inválido ou indisponível mantém o cadastro manual. A sugestão fica em sessão, expira e não grava o livro antes da confirmação do operador.

## ADR-009 — Relatórios com fórmula histórica explícita e CSV sem PII

**Status:** aceita.

**Contexto.** Relatórios por período precisam refletir o que estava aberto e vencido em uma data de referência, sem divergência entre tela e exportação.

**Decisão.** Reutilizar os mesmos construtores de consulta e filtros na tela e no CSV. Uma locação aberta na referência começou até a data e não encerrou antes de seu fim; o vencimento considera a primeira renovação posterior à referência, ou o prazo atual quando não houver essa renovação.

**Consequência.** O CSV transmite apenas dados agregados/de circulação autorizados, neutraliza fórmulas e não exporta nomes, e-mails ou identificadores de leitores. A demanda de reservas é fila atual e idade atual, pois o modelo não guarda uma linha do tempo completa de cada reentrada na fila.

## ADR-010 — Observabilidade agregada e minimização de dados

**Status:** aceita.

**Contexto.** O ambiente precisa permitir diagnóstico sem transformar logs e métricas em repositório de dados pessoais.

**Decisão.** Emitir logs JSON com request/correlation ID, rota, status, duração e resultado controlado; agregar métricas em buckets limitados. Exigir `manage-team` para a saúde operacional e ocultar DSNs e detalhes internos da resposta.

**Consequência.** É possível seguir requisição → outbox → job e detectar dependências degradadas, fila pendente/falha e scheduler atrasado. Eventos sob contenção podem ser amostrados ou descartados; os números não substituem auditoria. Corpo, query string, token, senha e PII não entram nos logs de telemetria.

## ADR-011 — Demonstração Docker local, sem alegação de nuvem

**Status:** aceita e validada para F7 local; imagem, Compose, seeder, jornadas principais, suíte MySQL, recuperação automática após falha induzida, rollback, deploy C, backup e restauração lateral foram exercitados.

**Contexto.** O portfólio precisa de uma forma reproduzível de demonstrar a aplicação sem utilizar o ambiente de desenvolvimento como publicação.

**Decisão.** Preparar um destino Docker local separado, com acesso HTTP em loopback e dados de demonstração. O projeto `biblioteca-demo` reúne app PHP 8.4/Apache, MySQL, dois Redis, Horizon, scheduler e Mailpit; o processo da aplicação roda como `www-data`, sem escrita no código, e os Blade compilados são isolados por release. Deploy, backup e restauração são controlados pela automação local e foram ensaiados sem promover a base lateral.

**Consequência.** Não se afirma HTTPS público, hospedagem em nuvem, backup gerenciado, escalonamento, observabilidade externa ou RabbitMQ. Credenciais locais permanecem fora do versionamento e a demonstração não deve conter dados pessoais reais. O CI remoto está preparado, mas ainda não foi executado; Mailpit valida somente SMTP local.
