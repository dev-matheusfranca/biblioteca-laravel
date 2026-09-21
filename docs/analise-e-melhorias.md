# Análise e melhorias da Biblioteca

## Propósito e fluxo

O projeto resolve a operação básica de uma biblioteca: manter o acervo organizado e saber, com segurança, quais exemplares podem circular.

```text
Autores + Categorias -> Livro (estoque e situação) -> Empréstimo -> Devolução -> Histórico
```

O cadastro de livro reúne autor, categoria, quantidades, ISBN e status. Só um livro `ativo` com quantidade disponível pode ser emprestado. Na retirada, o sistema reduz o saldo dentro de transação; na devolução, registra a data, aumenta o saldo até o total cadastrado e evita duplicidade. A situação `atrasada` é derivada da data prevista quando a devolução ainda não ocorreu.

Visitantes veem apenas o catálogo ativo e seus indicadores. Após autenticar, a conta acessa gestão de autores, categorias, livros e empréstimos. A implementação atual trata toda conta autenticada como administradora; a distinção entre equipe e leitor é uma lacuna de produto e segurança, não uma regra já implementada.

## Melhorias aplicadas

| Necessidade observada | Evidência no projeto | Melhoria entregue |
| --- | --- | --- |
| Visual genérico e disperso | Views Blade antes baseadas em componentes Bootstrap | Layout próprio azul-noite e lilás, estante visual, painéis, indicadores e hierarquia de navegação em CSS local. |
| Navegação em telas pequenas | Ações e listas exigem leitura compacta | Menu móvel, barras e tabelas com adaptação responsiva; paginação e ações preservam contexto. |
| Encontrar registros rapidamente | Listagens de livros, autores, categorias e empréstimos | Busca e filtros validados; filtros permanecem ao paginar. |
| Entender disponibilidade real | Estoque e status podem ter significados diferentes | Indicadores e etiquetas separam disponível, indisponível e cadastro inativo. |
| Evitar inconsistência de circulação | Empréstimos e devoluções alteram estoque | Transações, bloqueio do livro, prevenção de empréstimo duplicado e devolução idempotente. |
| Preservar rastreabilidade | Exclusões poderiam romper relações/histórico | Bloqueio de remoção de autores, categorias e livros dependentes; empréstimos não podem ser apagados pela interface. |
| Reduzir erro operacional | Formulários sem contexto e ações irreversíveis | Mensagens de validação, data mínima de devolução, estados vazios e confirmação antes de devolução/remoção. |
| Melhorar acessibilidade de uso | Navegação e feedback requerem percepção clara | Link para pular ao conteúdo, foco visível, rótulos, `aria-current`, avisos com papéis semânticos e ícones decorativos ocultos a leitores de tela. |

Os assets são `public/css/biblioteca.css` e `public/js/biblioteca.js`, referenciados no layout Blade. Isso mantém o visual disponível sem CDN nem processo de build no runtime.

### Refinamento dos seletores e do calendário

Os pop-ups nativos foram substituídos progressivamente por Tom Select (build base) e Flatpickr, com distribuições/licenças locais em `public/vendor/` e adaptações separadas em `select-picker` e `date-picker` (CSS/JS). A alteração mantém o layout aprovado, os nomes dos campos e os contratos do backend. `npm run vendor:sync` reproduz os assets usando as versões fixadas no lockfile.

- Selects: opções com destaque lilás, seleção marcada, busca, mensagem de ausência de resultados e menu posicionado fora dos painéis para evitar recorte. Filtros continuam aceitando a opção vazia e formulários mantêm `required` e valores previamente preenchidos.
- Datas: calendário em português, data visível em `dd/mm/aaaa`, envio ISO, limites mínimo/máximo e validação estrita da digitação. Seta para baixo entra na grade; setas navegam, Enter seleciona e Escape fecha.
- Validação deste refinamento em Chromium: seleção, busca/vazio, teclado, Escape, reset, campos obrigatórios, valores na edição, FormData sem envio de cadastro, datas inválidas/passadas, fallback sem JavaScript e viewports de 320, 390 e 1280 px. O calendário manteve sete colunas e não houve overflow horizontal. Na checagem final, console sem erros/avisos e assets sem falhas HTTP.
- A suíte PHP foi reexecutada: 18 testes e 138 assertions aprovados. Sintaxe JS/PHP e `git diff --check` passaram. Não houve mudança de regras do backend, cadastros ou schema nesta etapa. Safari/Firefox e dispositivos móveis físicos não foram testados.

## Riscos e próximos passos

| Prioridade | Ponto | Situação e próximo passo |
| --- | --- | --- |
| P1 | Permissões | Qualquer usuário registrado pode administrar todo o acervo. Implementar papéis e policies para separar equipe bibliotecária de leitor. |
| P2 | Proteção do login | Não há limitação de tentativas no fluxo de login. Adicionar throttle e testes correspondentes antes de publicar. |
| P2 | Pessoas leitoras | O empréstimo escolhe registros de `users`; não há perfil, limite de empréstimos ou histórico pessoal dedicado. Levantar regras de operação antes de modelar. |
| P2 | Notificações e cobrança | Não há lembretes, renovação nem fluxo de pendência. Validar política de prazo, renovação e canais antes de implementar. |
| P3 | Relatórios | A página inicial oferece operação imediata, mas não há relatórios por período, categoria ou atraso. Definir indicadores com a equipe antes de criar consultas. |
| P3 | Volume do catálogo | Detalhes de autores, categorias e livros carregam as relações completas. Paginar essas relações quando o volume justificar. |

## Validação e limites da entrega

- `php artisan test` no conjunto consolidado: **18 testes aprovados e 138 assertions**. Abrange cadastro, login válido/inválido, logout, rotação de sessão, autenticação das áreas de gestão, onboarding, filtros, estoque, status ativo/inativo, empréstimo, atraso, devolução idempotente e histórico protegido.
- Smoke em browser com banco SQLite temporário e dados sintéticos na porta `8001`: cadastro de conta, autor, categoria e livro; edição de categoria; empréstimo com livro pré-selecionado; cancelamento da confirmação; devolução com reposição do saldo; busca sem resultados; login inválido/válido e logout; erro de formulário em pt-BR mantendo os demais campos preenchidos.
- Responsividade: home, lista de livros, formulário de livro, empréstimos e edição de categoria verificadas em 390, 768 e 1440 pixels. As 15 combinações renderizaram sem erro HTTP e sem transbordamento horizontal da página. Tabelas possuem rolagem interna em telas estreitas. Menu móvel abre e fecha por Escape; o resumo de erros recebe foco. Console do smoke sem erros ou avisos.
- Evidências visuais locais em `output/playwright/`, ignoradas pelo Git. A nova interface usa somente assets locais; sem recursos de CDN no fluxo remodelado.
- A aplicação original foi mantida na porta `8000`, com a página inicial atendendo localmente. Nenhuma migration ou alteração do schema do banco original foi aplicada nesta entrega.
- Não há evidência suficiente para declarar o sistema pronto para produção. Segurança operacional, papéis, throttle e regras de leitores precisam da próxima etapa.
- SQLite em memória valida os efeitos sequenciais de estoque e devolução repetida. Concorrência real com bloqueios de linha em MySQL não foi reproduzida nesta entrega.

## Execução segura

Para banco novo, configure o `.env`, gere a chave e rode `php artisan migrate` de forma explícita. O script `composer setup` inclui migrations e tarefas de dependência/front-end, portanto requer revisão do ambiente antes do uso.
