# Operação da demonstração Docker

O destino aprovado para F7 é o computador local. `compose.demo.yaml` mantém aplicação, MySQL, Redis de fila, Redis de catálogo, Horizon, scheduler e Mailpit separados do desenvolvimento. As portas são publicadas somente no loopback: aplicação em `http://localhost:8089` e Mailpit em `http://localhost:8029`. Não há deploy público, DNS, TLS ou custo de cloud nesta entrega.

O script recusa endpoints Docker remotos (`tcp://` e `ssh://`), inspeciona e fixa o contexto local em cada comando e fixa o projeto Compose como `biblioteca-demo`. `.env.demo` aceita somente as oito variáveis geradas pelo inicializador. Isso evita que um contexto remoto ativo ou uma variável de projeto redirecione a operação.

## Preparar e publicar

Pré-requisitos: Docker Engine com Compose v2, Python 3.11+ e espaço para imagens e volumes. No Windows, use Docker Desktop com containers Linux. Execute na raiz do checkout:

```powershell
python scripts/demo.py init
python scripts/demo.py deploy --seed
python scripts/demo.py status
```

O script gera `.env.demo` local com chave e senhas aleatórias e nunca substitui um arquivo existente. Consulte as contas e o roteiro em [demonstracao.md](demonstracao.md). Os segredos de senha das contas entram somente no container efêmero de seed; os processos permanentes não recebem essas três variáveis. Credenciais necessárias ao banco continuam no ambiente local do serviço e devem ser protegidas pelo acesso ao host Docker.

A imagem usa PHP 8.4 com Apache, OPcache e dependências Composer sem ferramentas de desenvolvimento. O processo roda como `www-data`; código e dependências pertencem a root, com escrita limitada a `storage`, `bootstrap/cache` e diretórios de execução do Apache. O serviço não monta o código do checkout. O entrypoint prepara configuração, mas não executa migration nem seed por réplica.

Cada artefato recebe uma tag calculada a partir dos arquivos do runtime, lockfile, Dockerfile, Compose e `.dockerignore`; tags existentes são reutilizadas e nunca reconstruídas pelo pipeline. O estado registra o ID efetivo da imagem. As imagens anteriores permanecem locais para recuperação. Alterações em arquivos excluídos podem provocar uma nova tag conservadora, mesmo sem alterar a aplicação.

As views Blade compiladas usam um diretório por release dentro de `storage/framework/views`. Assim, trocar para uma imagem anterior não reaproveita uma view compilada pela imagem mais nova, nem precisa limpar o cache de outra versão. Os demais dados persistentes de storage continuam compartilhados pelo ambiente.

Um novo deploy constrói a imagem, valida a identidade atual, interrompe os três escritores, gera backup, inicia dependências, aplica migrations uma única vez e troca app, worker e scheduler. Ele só grava o novo estado depois dos canários do catálogo, login administrativo, integridade de empréstimos e saúde operacional. Essa operação provoca uma pequena indisponibilidade local; não oferece rolling deployment sem interrupção.

`.demo/state.json`, `.demo/attempt.json` e o lock serializam a operação e registram a última versão validada. Não edite ou apague esses arquivos para contornar um erro. Uma mudança na configuração de MySQL, Redis ou Mailpit bloqueia o fluxo automático: exige um ensaio específico de upgrade e recuperação antes de tocar nos volumes persistentes.

## Backup e restauração ensaiada

```powershell
python scripts/demo.py backup
python scripts/demo.py restore --archive __FILES/bd/<arquivo>.sql.zip
```

O backup pausa app, worker e scheduler durante a captura e os reinicia em seguida. O ZIP timestampado contém SQL, manifesto com origem, imagem e contagens, além de checksum SHA-256. O dump usa snapshot transacional. Arquivos sensíveis não são anexados à documentação nem versionados. Falhas em dump/import/seed não persistem sua saída bruta nos logs do script.

A restauração aceita somente um arquivo de `__FILES/bd/` identificado como backup de `biblioteca_demo`. Confere checksum e imagem, cria `biblioteca_demo_restore_<timestamp>` e importa com usuário temporário que só tem permissão no novo banco. Esse usuário é removido após a importação. O banco original não é substituído.

O canário de restauração compara contagens, integridade e acesso HTTP público/autenticado em `127.0.0.1:8091`. O container usa volume de storage, prefixos e cookie próprios, sem worker nem entrega SMTP. O script remove o container e o volume temporários do ensaio concluído; mantém o banco restaurado para inspeção. Um erro preserva a origem e a evidência necessária à investigação. A porta 8091 deve estar livre.

Este comando prova recuperação em banco lateral; não promove a cópia nem reseta o cenário ativo. Não há reset destrutivo automatizado. Um backup contém dados, mas não as chaves de aplicação nem a imagem: preserve separadamente `.env.demo` e os artefatos locais, com controle de acesso ao computador. Perder a chave ou remover a imagem pode inviabilizar a recuperação de sessões, autenticação ou canários do snapshot.

## Falha e rollback

```powershell
python scripts/demo.py recover
python scripts/demo.py rollback
```

`recover` retoma a última versão saudável registrada na tentativa interrompida. Ele não escolhe simplesmente o campo `previous`, que pode apontar para uma versão ainda mais antiga. Deploy e rollback guardam um journal antes da troca; uma falha tenta essa recuperação automaticamente. O journal é preservado se não houver condições seguras para avançar.

`rollback` exige imagem anterior disponível e migrations idênticas às da versão atual. Faz backup, troca somente o código, compara o canário e confirma o HTTP e a saúde antes de inverter o estado. Schema, empréstimos, reservas, sessões e intenções duráveis permanecem. Não executa `migrate:rollback`, restaura dados sobre a origem, apaga volumes ou tenta desfazer e-mails já aceitos. Com schema diferente, prepare uma correção compatível e valide-a no banco restaurado antes de promover qualquer mudança.

Se a primeira inicialização falhar, não existe versão anterior. O script para os escritores e mantém o banco. Após corrigir a causa ambiental, `python scripts/demo.py resume-init` retoma exclusivamente o mesmo artefato e as mesmas dependências registrados. Uma falha no código da primeira imagem exige análise do journal e uma nova estratégia de recuperação; não remova estado nem banco para ocultá-la.

Para incidente local, consulte primeiro `python scripts/demo.py status`, `/operacao/saude`, `/operacao/comunicacoes` e Horizon com uma conta administrativa. Corrija a dependência ou use `recover`; reenvie uma comunicação falha somente pela operação autorizada, considerando a possibilidade documentada de duplicidade após aceitação SMTP. Consulte [observabilidade.md](observabilidade.md).

## Pipeline e limites

`.github/workflows/quality.yml` valida PHP, assets, testes SQLite/MySQL/Redis e a imagem de desenvolvimento. O job `demo` também executa guardas Python, deploy com seed, backup e restore autenticado em runner isolado. O workflow está preparado no checkout; sua execução remota depende de publicação no GitHub e não deve ser apresentada como comprovada nesta entrega local.

O ensaio local mede tempo de restauração observado, sem assumir SLA de RTO/RPO. A automação não agenda backups recorrentes nem exporta cópias para outro computador. Como todos os serviços ficam no mesmo host, uma perda desse host afeta aplicação, dados e backups locais. Uma futura publicação deverá definir destino, orçamento, TLS, segredos, cópia externa, retenção, monitoramento e objetivos de recuperação.

As evidências reais desta entrega estão em [execucao-f2-f7.md](execucao-f2-f7.md); capacidades e limites de desempenho estão em [desempenho.md](desempenho.md).
