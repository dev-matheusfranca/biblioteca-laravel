# Relatórios operacionais

Os relatórios ficam em `/relatorios` e exigem sessão autenticada, conta ativa e a permissão `manage-library`. Eles não exibem nome, e-mail ou outro dado pessoal do leitor. O identificador do empréstimo é mantido no detalhamento para conciliação operacional.

## Período e referência

O relatório de circulação separa dois conceitos:

- **fluxo do período**: eventos ocorridos entre `period_start` e `period_end`, inclusive;
- **posição na referência**: estado do empréstimo ao fim de `reference_date` em `America/Sao_Paulo`.

Sem filtros, o período cobre os últimos 30 dias até hoje e a referência é o fim do período. O intervalo máximo é de 366 dias. Informar apenas o início ou o fim completa o outro limite antes da validação.

Um empréstimo estava aberto na referência quando:

```text
data_locacao <= reference_date
e (encerrado_em é nulo ou encerrado_em > fim do dia de referência)
```

O prazo histórico considera a primeira renovação registrada depois do fim do dia de referência, ordenada por `created_at` e `id`. Nesse caso, `previous_due_date` recompõe o prazo que valia na referência; sem renovação posterior, vale `data_devolucao`. Um aberto é atrasado quando esse prazo histórico é anterior a `reference_date`.

Os indicadores de empréstimos, devoluções, perdas e solicitações de reserva contam eventos. A demanda por título mostra separadamente retiradas e solicitações de reserva e não tenta deduplicar leitores.

O detalhamento pode mostrar:

- empréstimos iniciados no período;
- empréstimos abertos na referência;
- empréstimos atrasados na referência.

Na população iniciada no período, o prazo exibido é o prazo atual armazenado. Nas populações abertas e atrasadas, o prazo exibido é o prazo histórico recomposto para a referência.

O filtro `q` restringe por título os indicadores, a demanda, o detalhamento, a fila e o inventário.

## Fila e inventário atuais

A fila contém somente reservas atualmente em `aguardando` com `active_key`. A posição é calculada por título, `created_at` e `id`. A idade usa um único instante de geração para toda a resposta. Como `disponivel_em` pode ser apagado e regravado quando uma reserva volta à fila, o sistema não apresenta média histórica de espera.

O inventário indisponível é uma posição atual. Cada exemplar recebe um único motivo segundo esta precedência:

1. identificação física pendente;
2. manutenção;
3. extravio;
4. baixa;
5. empréstimo aberto;
6. hold de reserva;
7. título em reconciliação;
8. título inativo.

Assim, uma unidade livre de um título inativo ou ainda em reconciliação também aparece como indisponível para o público.

## CSV

Cada tela possui um CSV gerado por streaming, sem arquivo persistido. A consulta, os filtros e a ordenação são os mesmos da tela. As respostas usam `Cache-Control: no-store, private` e incluem `X-Report-Generated-At` e `X-Report-Timezone`; circulação inclui também início, fim e referência.

O CSV de circulação contém linhas de metadados, indicadores, demanda por título e a população de empréstimos selecionada. O separador é ponto e vírgula, o arquivo usa UTF-8 com BOM e o escape segue o formato CSV padrão. Campos que começam com tabulação, quebra de linha ou retorno de carro, ou que possuem uma fórmula após espaços/controles, recebem apóstrofo para evitar execução por planilhas.

## Rotas

| Tela | HTML | CSV |
|---|---|---|
| Circulação e demanda | `GET /relatorios/circulacao` | `GET /relatorios/circulacao.csv` |
| Fila atual | `GET /relatorios/fila` | `GET /relatorios/fila.csv` |
| Exemplares indisponíveis | `GET /relatorios/indisponiveis` | `GET /relatorios/indisponiveis.csv` |

As listagens aceitam `per_page` com 10, 25 ou 50 itens. As relações de livros por autor, livros por categoria e empréstimos por livro também são paginadas para evitar carregar históricos integrais na página.

## Limite do ensaio de desempenho

O `EXPLAIN ANALYZE` local usou 5.000 títulos, 15.000 exemplares e 20.000 empréstimos encerrados. As consultas medidas ficaram abaixo de 150 ms nesse conjunto e os subselects de renovação, empréstimo ativo e hold usaram índices existentes, por isso nenhum índice específico de relatórios foi adicionado. O conjunto não possuía reservas ativas; o tempo observado para a fila não comprova comportamento sob uma fila grande. Antes de assumir escala maior, repita o ensaio com volume representativo de reservas aguardando e com um histórico de empréstimos superior ao atual.
