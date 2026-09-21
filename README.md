# Biblioteca

Aplicação Laravel para gerir um acervo e a circulação de livros. Ela dá a uma equipe um único lugar para cadastrar autores, categorias e títulos, acompanhar exemplares disponíveis e registrar empréstimos e devoluções.

## O que o sistema faz

- Mantém autores e categorias que organizam o acervo.
- Cadastra livros com ISBN opcional, quantidade total, quantidade disponível e situação de cadastro (`ativo` ou `inativo`).
- Registra empréstimos para uma pessoa com data prevista de devolução e impede um novo empréstimo aberto do mesmo livro para a mesma pessoa.
- Registra devoluções de forma idempotente, devolvendo um exemplar ao estoque uma única vez.
- Calcula a situação atrasada a partir da data prevista; não depende de uma atualização manual de status.
- Protege o histórico: autores, categorias e livros que possuem dependências não são removidos; empréstimos não têm rota de exclusão.
- Oferece filtros por texto, categoria, disponibilidade e situação, com paginação que conserva a consulta.

A página inicial mostra apenas títulos ativos para visitantes; contas autenticadas também veem o panorama operacional e títulos inativos. As telas de gestão exigem autenticação. No estado atual, qualquer conta registrada pode administrar o acervo; papéis distintos de equipe e leitor precisam ser definidos antes de uma publicação aberta.

## Experiência e interface

A interface usa Blade e arquivos locais em `public/css/biblioteca.css` e `public/js/biblioteca.js`: não depende de CDN e não exige build do front-end em tempo de execução. A direção visual combina navegação azul-noite, acentos lilás e ilustração de estante, com formulários, filtros, estados vazios, alertas e tabelas responsivas. Inclui menu móvel, link para pular ao conteúdo, foco visível e confirmação antes de ações destrutivas ou operacionais.

Os menus de seleção usam Tom Select 2.6.2 (build base) e o calendário usa Flatpickr 4.6.13, em português. As adaptações visuais e de formulário ficam em `public/css/{select,date}-picker.css` e `public/js/{select,date}-picker.js`. Os componentes preservam os campos originais e os valores esperados pelo backend; a data aparece como `dd/mm/aaaa` e é enviada como `aaaa-mm-dd`. Sem JavaScript, os controles nativos continuam disponíveis.

As distribuições e licenças estão versionadas em `public/vendor/`. Para reproduzir esses assets após instalar as versões fixadas no lockfile:

```powershell
npm ci --ignore-scripts
npm run vendor:sync
```

Node é necessário apenas para essa sincronização, não para servir as telas.

## Requisitos

- PHP 8.2 ou superior
- Composer
- Banco de dados configurado no `.env` (ou SQLite para desenvolvimento/testes)

O projeto usa Laravel 12 e PHPUnit. Os pacotes de Vite/Tailwind constam do repositório-base, mas as telas entregues carregam os assets locais diretamente.

## Execução local

1. Instale as dependências, caso `vendor/` ainda não exista:

   ```powershell
   composer install
   ```

2. Crie o arquivo de ambiente somente se ele ainda não existir e configure a conexão de banco:

   ```powershell
   if (-not (Test-Path .env)) {
       Copy-Item .env.example .env
       php artisan key:generate
   }
   ```

3. Em um banco novo, aplique as migrations explicitamente:

   ```powershell
   php artisan migrate
   ```

   Não execute `composer setup` sem revisar o ambiente: esse script inclui migration e instalação/build de dependências front-end.

4. Inicie a aplicação:

   ```powershell
   php artisan serve
   ```

   Acesse `http://127.0.0.1:8000`.

## Validação

```powershell
php artisan test
```

A suíte usa SQLite em memória e cobre cadastro, login, logout, sessões, acesso às telas, catálogo público e autenticado, filtros, estoque, livro ativo/inativo, empréstimos, atraso derivado, devolução idempotente e preservação de histórico. O resultado consolidado desta entrega é **18 testes e 138 assertions**.

Consulte [docs/analise-e-melhorias.md](docs/analise-e-melhorias.md) para a leitura de processo, as melhorias aplicadas e os próximos riscos.
