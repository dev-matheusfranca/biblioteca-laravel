# API v1 da biblioteca

A API usa JSON e está disponível sob `/api/v1`. O catálogo é público. Os demais endpoints exigem um token pessoal Sanctum no cabeçalho `Authorization: Bearer <token>`. Sessões do navegador não autenticam a API.

Leitores ativos criam tokens em **Minha conta > Tokens de API**. Cada token dura 24 horas, possui um subconjunto explícito das permissões abaixo e tem o segredo exibido uma única vez:

- `personal:read`: consultar conta, empréstimos e reservas próprios;
- `reservations:write`: criar e cancelar reservas próprias;
- `loans:renew`: renovar empréstimos próprios.

As mutações exigem `Idempotency-Key` com UUID. A mesma chave e a mesma requisição repetem a resposta persistida sem repetir a alteração. Reutilizar a chave para outro método, caminho ou corpo retorna `409`. As respostas ficam disponíveis para replay por 24 horas e são removidas pelo comando agendado `api:idempotency-clean`.

## Endpoints

| Método | Caminho | Permissão |
|---|---|---|
| GET | `/api/v1/catalogo` | público |
| GET | `/api/v1/catalogo/{livro}` | público |
| GET | `/api/v1/me` | `personal:read` |
| GET | `/api/v1/me/emprestimos` | `personal:read` |
| GET | `/api/v1/me/emprestimos/{locacao}` | `personal:read` |
| GET | `/api/v1/me/reservas` | `personal:read` |
| POST | `/api/v1/livros/{livro}/reservas` | `reservations:write` |
| DELETE | `/api/v1/reservas/{reserva}` | `reservations:write` |
| POST | `/api/v1/emprestimos/{locacao}/renovacoes` | `loans:renew` |

Listagens aceitam `page` e `per_page` entre 1 e 50. O catálogo também aceita `q` e `categoria`. Erros seguem `message`, `request_id` e, em validações, `errors`. O mesmo identificador aparece no cabeçalho `X-Request-Id`.

O contrato completo está em [openapi.yaml](openapi.yaml). O cliente de demonstração `node scripts/api-demo.mjs` consulta `http://localhost:8088/api/v1` por padrão. Defina `BIBLIOTECA_API_TOKEN` para consultar a área pessoal. Uma mutação só ocorre quando for informado um argumento explícito, por exemplo `--reserve-book=12`. Use `--idempotency-key=<UUID>` para repetir a mesma tentativa após uma resposta perdida; sem esse argumento, o script gera uma chave nova. O cliente nunca imprime o token.

A revogação impede novas mutações mesmo quando a requisição passou pela autenticação pouco antes do clique de revogar. Consultas GET que já estiverem em execução não são canceladas retroativamente; elas continuam limitadas aos dados do próprio leitor.

Trocas de senha, alterações de papel e inativação revogam tokens pessoais e sessões web persistidas. A invalidação coordenada de sessões usa o driver `database`, que é o driver adotado na demonstração Docker e no destino local desta entrega. Se outro driver for adotado futuramente, ele precisa oferecer uma estratégia equivalente de revogação antes da mudança de ambiente.
