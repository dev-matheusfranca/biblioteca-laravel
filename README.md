# Biblioteca Laravel

Aplicação de biblioteca para portfólio, construída como um monólito Laravel: a equipe administra acervo e circulação, enquanto leitores usam o próprio portal. O projeto prioriza regras de negócio verificáveis, inventário físico, acesso por papéis, concorrência no banco e operação local reproduzível.

**Situação atual:** as fases F2–F7 foram concluídas e validadas para uma demonstração Docker local. A F7 comprovou imagem PHP 8.4/Apache, Compose separado, cenário fictício, recuperação, rollback, deploy C, backup verificado e restauração lateral. O projeto não declara publicação em nuvem ou operação externa.

## Capacidades demonstradas

- Acervo com autores, categorias, títulos e exemplares físicos identificados; reconciliação explícita antes de novas retiradas.
- Empréstimos, devoluções, perdas, atrasos, reservas FIFO, holds e renovação sob política versionada.
- Portal de leitor, gestão por bibliotecários e administração de equipe; autorização é aplicada no servidor.
- Comunicações por outbox durável, Redis e Horizon, com avisos no portal e caixa Mailpit local.
- Busca ISBN limitada e com fallback manual, API autenticada para fluxos do leitor e relatórios com CSV seguro.
- Catálogo público cacheado em Redis dedicado, fallback ao banco e painel de saúde com telemetria agregada.

## Como explorar

O [guia de desenvolvimento](docs/desenvolvimento.md) contém o início do ambiente Docker isolado, provisionamento do primeiro administrador, operações de fila e os limites do runtime local. Ele também registra as portas de desenvolvimento, que ficam expostas apenas em loopback.

Para entender o projeto como case técnico, comece por:

- [Arquitetura](docs/arquitetura.md): componentes, fluxos e fronteiras do sistema.
- [Decisões de arquitetura](docs/decisoes.md): escolhas, consequências e limites conhecidos.
- [Execução F2–F7](docs/execucao-f2-f7.md): evidências por fase, incluindo testes, corridas MySQL e smoke de navegador.
- [Demonstração](docs/demonstracao.md) e [operação local](docs/deploy-local.md): roteiro, dados fictícios, backup, restauração e rollback da F7.
- [API](docs/api.md), [integração ISBN](docs/integracao-isbn.md), [relatórios](docs/relatorios.md) e [observabilidade](docs/observabilidade.md): contratos das superfícies especializadas.
- [Plano de evolução](docs/plano-evolucao.md): histórico e sequência de evolução do projeto.

## Qualidade e evidências

A F6 registrou 141 testes e 887 asserções em SQLite, além de 152 testes e 958 asserções em MySQL. A F7 concluiu o deploy local da imagem C, cenário por seeder, jornadas de renovação, FIFO, comunicação, relatórios e permissões, recuperação automática, rollback, backup e restauração lateral autenticada. A restauração preservou 5 usuários, 14 títulos, 30 exemplares, 5 locações e 3 reservas, sem locações abertas inválidas. Foram incluídas corridas reais de concorrência com processos MySQL separados, checagens estáticas e smoke responsivo de telas de gestão, portal e relatórios. As evidências e seus limites estão em [docs/execucao-f2-f7.md](docs/execucao-f2-f7.md).

Essa validação não é um teste de carga, certificação de entrega SMTP externa, publicação em nuvem ou garantia de escala. Mailpit representa a caixa de correio local; métricas operacionais são amostradas e não substituem auditoria completa.

## Limites assumidos

- Uma única biblioteca: não há multiempresa nem isolamento por organização.
- A demonstração F7 é local em Docker, em serviços e volumes separados do desenvolvimento; não há HTTPS público, provedor cloud ou RabbitMQ declarados.
- O envio de e-mail usa uma outbox para preservar a intenção, mas SMTP não oferece garantia de entrega exatamente uma vez.
- O catálogo pode servir resultado em cache até a invalidação/TTL; em falha do cache, consulta o banco.
- ISBN é uma sugestão de edição da Open Library: o operador continua responsável pela confirmação e pelo cadastro manual.
- O CI remoto está preparado, mas não teve execução remota; o ganho de p95 no benchmark local foi 14,73% (352,33 ms para 300,44 ms), abaixo da meta exploratória de 20% (281,86 ms), sem constituir promessa de escala ou SLA.

## Desenvolvimento

O projeto requer PHP 8.4, Composer, Node e Docker Desktop com engine Linux/WSL2 para o ambiente recomendado. A documentação explica como criar segredos locais ignorados pelo Git e como iniciar aplicação, banco, worker e scheduler na ordem correta. Não use arquivos de ambiente, bases ou credenciais reais como dados de demonstração.

Para validações locais, consulte os comandos e o escopo de cada suíte no [guia de desenvolvimento](docs/desenvolvimento.md). O projeto inclui verificações de testes, análise estática, estilo PHP e assets, com evidência registrada por fase.
