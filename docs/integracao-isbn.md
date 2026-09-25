# Consulta bibliográfica por ISBN

A equipe abre **Acervo → Consultar ISBN**, pesquisa uma edição e confirma **Usar sugestão no cadastro**. A consulta e sua confirmação não gravam livros, autores ou exemplares. O formulário normal recebe título, ISBN e ano; a equipe confere a edição física, escolhe autor/categoria e salva. Dados já cadastrados não são sobrescritos.

## Contrato e fonte

O backend usa `GET https://openlibrary.org/search.json` com `q=isbn:{isbn}`, `limit=1` e uma lista explícita de campos de obra e edição. O endereço não vem do cliente, redirects estão desativados e ISBN-10/13 é normalizado e validado por checksum. O endpoint legado `/api/books` não é usado.

Só uma edição inequívoca, com o ISBN pesquisado entre seus identificadores, pode gerar uma sugestão. Título e ano vêm de `editions.docs[0]`, não da obra. Nomes de autores vêm de `author_name` da obra e são identificados como tal na tela; a seleção do autor cadastrado continua manual. O link externo é construído no servidor a partir de um identificador `/books/OL…M` validado.

Referências oficiais consultadas em 25/09/2026:

- [Search API](https://openlibrary.org/dev/docs/api/search): campos específicos e distinção entre obra e edição.
- [Books / ISBN API](https://openlibrary.org/dev/docs/api/books): identificadores de edição e classificação da Books API antiga como legado.
- [Política de uso das APIs](https://openlibrary.org/developers/api): cache, uso interativo e limites de requisição; consultas em massa devem usar os dumps fornecidos pelo projeto.

## Limites e falhas

- Uma chamada por segundo por aplicação, protegida por lock compartilhado. O identificador enviado é `BibliotecaPortfolio/1.0`; não há contato fictício nem uso da cota ampliada para clientes identificados com contato real.
- Timeout de conexão de 2 segundos e total de 5 segundos por tentativa. Uma retentativa, após 1 segundo, apenas para falha de conexão ou HTTP 500/502/503/504. HTTP 404/429 não é repetido.
- Resposta limitada a 64 KiB, campos e tipos conferidos, título de até 255 caracteres e no máximo cinco nomes de autores. HTML de terceiros é exibido como texto escapado.
- Cache de sucesso por sete dias e de ausência/ambiguidade por uma hora. Falhas transitórias não são transformadas em resultado positivo. A mesma entrada com espaços/hífens normaliza para a mesma chave.
- Sugestão mantida na sessão por até 15 minutos e consumida uma vez. A confirmação usa apenas os dados guardados pelo servidor; campos enviados pelo cliente não podem alterar a sugestão.
- Dez consultas por minuto por usuário autenticado, além do limite global do provedor. Leitor e visitante não acessam a integração.

Indisponibilidade, JSON inválido, edição ambígua, ISBN divergente ou limitação de chamadas mantêm o caminho **Continuar cadastro manual**. O serviço externo não participa da transação de cadastro.

## Evidência

Consulta real executada no container local em 25/09/2026 para o ISBN público `9780140328721`: HTTP 200, edição `OL7353617M`, título `Fantastic Mr. Fox`, ano 1988; a obra retornada tinha título `Fantastic Mr Fox`, mostrando por que o cadastro usa os campos da edição. A consulta foi somente leitura e não gravou catálogo. Os testes de contrato usam respostas controladas para exercer falhas sem sobrecarregar o provedor.

Em consultas posteriores, a conexão externa apresentou `curl 28 — SSL connection timeout`, reproduzido também com Guzzle diretamente. A tela manteve o cadastro manual acessível. Para concluir o ensaio visual de sucesso sem depender dessa oscilação externa, a resposta pública já observada foi reproduzida no cache local, passando pelo mesmo parser. O navegador então mostrou a edição, exigiu confirmação e preencheu título/ISBN/ano. A contagem de livros permaneceu em cinco antes e depois da confirmação. Essa evidência distingue o primeiro acesso externo real, a falha real posterior e o teste de interface com cache preenchido.

`BibliographicLookupTest`: nove testes e 51 asserções, cobrindo autorização, checksum, cache, edição correta, resposta inválida, HTML escapado, retentativa e confirmação protegida por sessão/expiração. O formulário e a sugestão foram conferidos a 390 pixels, sem transbordamento; captura local em `output/playwright/f5-isbn-mobile.png`.
