# Demonstração do portfólio

A demonstração roda somente no computador local, em um projeto Docker separado chamado `biblioteca-demo`. A aplicação usa `http://localhost:8089` e o Mailpit usa `http://localhost:8029`. O ambiente de desenvolvimento em `8088` e seus volumes não são reutilizados.

## Inicialização segura

Execute os comandos na raiz do projeto:

```powershell
python scripts/demo.py init
python scripts/demo.py deploy --seed
python scripts/demo.py status
```

`init` cria `.env.demo` com chaves e senhas aleatórias e preserva um arquivo existente. Esse arquivo é local, ignorado pelo Git e não deve ser copiado para documentação, mensagem ou commit. As contas são:

| Perfil | E-mail | Variável da senha |
|---|---|---|
| Administrador | `admin@biblioteca.example.test` | `DEMO_ADMIN_PASSWORD` |
| Equipe | `equipe@biblioteca.example.test` | `DEMO_STAFF_PASSWORD` |
| Leitor principal | `leitor@biblioteca.example.test` | `DEMO_READER_PASSWORD` |
| Leitor da fila | `fila@biblioteca.example.test` | `DEMO_READER_PASSWORD` |
| Leitor com atraso | `atraso@biblioteca.example.test` | `DEMO_READER_PASSWORD` |

Abra `.env.demo` localmente para consultar uma senha quando necessário. O script e o seeder não imprimem credenciais.

`deploy --seed` é a única inicialização que carrega o cenário fictício. A carga exige explicitamente `DEMO_ENABLED=true`, `APP_ENV=production`, MySQL e o banco `biblioteca_demo`. Nos testes, a única exceção é SQLite `:memory:` com `APP_ENV=testing`. Senhas são obrigatórias, externas e distintas entre administração, equipe e leitores.

O seeder só aceita um banco de domínio vazio. Ele serializa a inicialização pelo lock da política de circulação e grava o marcador auditável `demo.initialized` no mesmo commit. Reaplicar a carga com o marcador válido não atualiza senha, prazo ou dado algum. Encontrar dados sem o marcador encerra a operação. Ele não executa `truncate`, `migrate:fresh` ou remoção de volume.

## Cenário carregado

O conjunto contém somente dados fictícios:

- 5 contas, 5 autores, 5 categorias, 14 títulos e 30 exemplares;
- unidades disponíveis, em manutenção, extraviada e baixada;
- um histórico de devolução e outro de perda;
- dois empréstimos atuais do leitor principal, incluindo um com vencimento em dois dias;
- um empréstimo atrasado de outro leitor;
- duas reservas aguardando a última unidade e uma reserva já separada;
- três intenções duráveis na outbox: reserva disponível, vencimento próximo e atraso.

A carga usa as mesmas actions de empréstimo, encerramento, reserva, alocação, inventário e comunicação da aplicação. Ela prepara a outbox, mas não envia e-mail diretamente. O deploy publica as intenções e então inicia Horizon e o scheduler; portal e Mailpit recebem as mensagens pelo fluxo assíncrono real.

## Roteiro principal de 3 a 5 minutos

1. **Catálogo público — 30 segundos.** Abra `http://localhost:8089/catalogo`, pesquise por `Mensageria` ou `Docker` e mostre disponibilidade por exemplar. Explique que cadastro público cria somente leitor e não concede acesso à gestão.
2. **Portal do leitor — 60 segundos.** Entre como `leitor@biblioteca.example.test`. Mostre os dois empréstimos, o prazo absoluto do lembrete e a reserva aguardando. Abra os avisos. Em **Tokens de API**, destaque abilities explícitas, validade de 24 horas e exibição única do segredo, sem precisar emitir um token durante a apresentação.
3. **Circulação da equipe — 60 segundos.** Entre como `equipe@biblioteca.example.test`. Localize o empréstimo de **A Última Unidade** e registre a devolução. A fila FIFO passa a separar a cópia para `fila@biblioteca.example.test` e grava uma nova intenção durável. Mostre também os exemplares em manutenção, baixa e extravio.
4. **Relatórios — 45 segundos.** Abra `/relatorios/circulacao`, compare eventos do período com abertos e atrasados na data de referência e filtre por título. Exporte o CSV e mostre metadados, indicadores, demanda e empréstimos sem nome ou e-mail de leitores. Passe por `/relatorios/fila` e `/relatorios/indisponiveis`.
5. **Operação — 45 segundos.** Entre como administrador e abra `/operacao/saude`, `/operacao/comunicacoes` e `/horizon`. Mostre correlação, tentativas, estado da outbox e acesso restrito. Em `http://localhost:8029`, confirme as mensagens fictícias capturadas pelo Mailpit.

Se houver menos tempo, use os passos 1, 2 e 4. A devolução do passo 3 altera o cenário; faça-a somente quando a apresentação incluir o fluxo assíncrono.

## Jornadas adicionais

- `fila@biblioteca.example.test`: possui uma reserva aguardando e outra disponível para retirada; serve para demonstrar FIFO e notificação de disponibilidade.
- `atraso@biblioteca.example.test`: possui empréstimo vencido; serve para demonstrar bloqueio de nova retirada e renovação, além do lembrete de atraso.
- Equipe: pode registrar retirada, devolução e perda, reconciliar inventário, consultar ISBN como sugestão e exportar relatórios.
- Administrador: pode alterar parâmetros versionados, gerir equipe, acompanhar comunicações, saúde e Horizon.
- API: crie um token no portal somente se a apresentação precisar de uma chamada real. Use o cliente documentado em [api.md](api.md), passe o token por variável de ambiente e revogue-o ao terminar.

## Preservação, backup e retorno ao cenário

Consulte o estado e gere backup antes de uma apresentação mutante:

```powershell
python scripts/demo.py status
python scripts/demo.py backup
```

Backups ficam em `__FILES/bd/` com dump, manifesto e checksum. Para ensaiar uma restauração sem substituir a origem:

```powershell
python scripts/demo.py restore --archive __FILES/bd/<arquivo>.sql.zip
```

A restauração cria outro banco, compara as contagens do pré-dump e executa canários público e autenticado em uma porta temporária. `rollback` troca somente o artefato de código pela imagem anterior validada e preserva schema, dados e eventos pendentes:

```powershell
python scripts/demo.py rollback
```

Reexecutar `deploy --seed` não restaura o estado inicial, porque o seeder é intencionalmente idempotente. `restore` é um ensaio em banco lateral e não promove esse banco nem reinicia o cenário ativo. Para repetir uma jornada, use novos registros fictícios pela interface; o reset destrutivo do cenário não faz parte desta automação. Veja o runbook [deploy-local.md](deploy-local.md). Não remova volumes como procedimento de recuperação.

## Limites honestos

- É uma demonstração local e não representa um deploy público, alta disponibilidade ou entrega de e-mail pela internet.
- Mailpit prova o fluxo SMTP local. Uma falha depois da aceitação pelo servidor e antes do commit do job ainda pode duplicar e-mail; portal e outbox permanecem idempotentes por evento.
- A busca ISBN depende de rede externa e pode atingir timeout; o cadastro manual continua disponível.
- O benchmark de fila foi executado sem reservas ativas em massa. Ele não sustenta promessa de escala para uma fila grande.
- Os dados são mutáveis durante a apresentação. O marcador impede misturar novamente a carga inicial com um banco já usado.
- Senhas e artefatos de backup são locais. Não inclua `.env.demo`, `.demo/`, `__FILES/bd/`, tokens ou capturas autenticadas no portfólio.
