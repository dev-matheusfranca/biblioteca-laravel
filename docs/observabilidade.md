# Observabilidade operacional

## Finalidade e limites

A aplicação registra logs estruturados em JSON e métricas agregadas de curta duração. Os eventos não incluem corpo de requisição, query string, tokens, e-mail, payload da outbox, DSN, senha ou exceções brutas. O `request_id` é gerado no limite HTTP e é reutilizado como `correlation_id` ao registrar uma comunicação; o job registra somente o identificador da outbox, a correlação, o resultado e a duração.

As métricas HTTP e dos jobs de comunicação ficam no cache operacional em buckets de um minuto. Cada bucket expira em setenta minutos; as leituras percorrem no máximo os últimos sessenta minutos. Elas mostram contagem, erros, média e maior latência, sem séries por leitor, livro ou mensagem. O contador de erro HTTP inclui respostas 4xx e 5xx. Um incremento que não obtiver o lock curto do bucket é descartado, portanto as métricas são uma amostra operacional, não uma auditoria exata. Se cache ou telemetria falhar, a operação observada continua. Um processamento de job concluído representa somente uma tentativa encerrada; ele pode ser uma operação idempotente sem um novo e-mail.

## Saúde e acesso

`GET /operacao/saude` exige sessão autenticada, conta ativa e permissão `manage-team`. A resposta HTML e JSON retorna `503` quando algum componente exigido está degradado e usa `Cache-Control: no-store`. Ela verifica separadamente o banco, a fila Redis, o cache operacional, o Redis LRU do catálogo e o heartbeat do agendador. Também mostra a profundidade da fila `communications`, totais/idade da outbox e códigos de alerta acionáveis.

O teste Redis e as leituras de outbox são isolados: uma indisponibilidade não quebra a página e não revela host, credencial ou mensagem de erro.

## Operação

O scheduler executa `operacoes:heartbeat` a cada minuto. O heartbeat é considerado atrasado após 180 segundos.

```powershell
docker compose --env-file .env.docker exec scheduler php artisan operacoes:heartbeat
docker compose --env-file .env.docker exec app php artisan operacoes:saude --json
docker compose --env-file .env.docker exec scheduler php artisan operacoes:saude --scheduler-only --json
docker compose --env-file .env.docker exec app php artisan operacoes:limpar-metricas
```

Para o healthcheck de inicialização do app, use `operacoes:saude --json --without-scheduler`; ele verifica banco, cache e Redis sem depender do processo de scheduler que ainda está iniciando. A verificação completa continua sendo a referência para a tela operacional depois da inicialização.

Quando a tela estiver degradada:

1. Banco ou cache: confirme o serviço e as migrations; não remova volumes ou registros para restaurar o estado.
2. Fila Redis: confirme Redis e Horizon, e só reinicie processos depois de identificar a indisponibilidade.
3. Scheduler atrasado: confirme `schedule:work`, o comando `operacoes:heartbeat` e os logs JSON.
4. Outbox falha: corrija a causa, reprocese pelo painel Comunicações e confirme o efeito no portal ou Mailpit. Pendências com `next_attempt_at` futuro não alertam antes do backoff vencer; falhas terminais, pendências vencidas por mais de 120 segundos e leases de processamento vencidos alertam.

O comando de saúde usa código de saída `0` quando saudável e `1` quando degradado. `--scheduler-only` não consulta banco ou Redis e é apropriado para o processo scheduler; `--without-scheduler` evita dependência circular no startup do app. `operacoes:limpar-metricas` remove somente buckets expirados com o prefixo `operations:metrics:v1` quando o cache operacional usa banco; agende-o periodicamente.
