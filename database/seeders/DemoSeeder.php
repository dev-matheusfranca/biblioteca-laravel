<?php

namespace Database\Seeders;

use App\Actions\Circulation\CheckoutExemplar;
use App\Actions\Circulation\CloseLoan;
use App\Actions\Circulation\CreateReservation;
use App\Actions\Communication\PrepareCommunicationEvents;
use App\Actions\Inventory\SynchronizeLivroAvailability;
use App\Enums\ExemplarCondition;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Autor;
use App\Models\Categoria;
use App\Models\CirculationPolicy;
use App\Models\Exemplar;
use App\Models\Livro;
use App\Models\User;
use App\Services\Catalog\PublicCatalog;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DemoSeeder extends Seeder
{
    private const MARKER_ACTION = 'demo.initialized';

    /** @var list<string> */
    private const ACCOUNT_EMAILS = [
        'admin@biblioteca.example.test',
        'equipe@biblioteca.example.test',
        'leitor@biblioteca.example.test',
        'fila@biblioteca.example.test',
        'atraso@biblioteca.example.test',
    ];

    /** @var list<string> */
    private const DOMAIN_TABLES = [
        'users',
        'autores',
        'categorias',
        'livros',
        'exemplares',
        'locacoes',
        'reservas',
        'renovacoes',
        'communication_outbox',
        'portal_notices',
        'audit_logs',
        'personal_access_tokens',
        'api_idempotency_records',
    ];

    public function run(): void
    {
        $this->assertSafeTarget();
        $passwords = $this->passwords();

        $seeded = DB::transaction(function () use ($passwords): bool {
            $policy = CirculationPolicy::query()->where('active_key', 'current')->lockForUpdate()->first();
            if (! $policy) {
                throw new RuntimeException('A política de circulação não existe. Aplique todas as migrations antes da demonstração.');
            }

            $marker = AuditLog::query()->where('action', self::MARKER_ACTION)->lockForUpdate()->first();
            if ($marker) {
                $this->assertKnownDataset($marker);

                return false;
            }

            $this->assertEmptyDomain();
            $users = $this->createAccounts($passwords);
            [$books, $copies] = $this->createCatalog();
            $this->createCirculationScenarios($users, $books, $copies);
            app(PrepareCommunicationEvents::class)->execute();

            AuditLog::create([
                'action' => self::MARKER_ACTION,
                'actor_id' => $users['admin']->id,
                'metadata' => [
                    'dataset_version' => (string) config('demo.dataset_version'),
                    'accounts' => count(self::ACCOUNT_EMAILS),
                    'books' => count($books),
                    'copies' => array_sum(array_map('count', $copies)),
                ],
            ]);

            return true;
        }, 3);

        if ($seeded) {
            app(PublicCatalog::class)->invalidate();
            $this->command->info('Cenário fictício da demonstração criado. As credenciais permanecem somente no ambiente local.');
        } else {
            $this->command->info('Cenário fictício já inicializado; nenhuma alteração foi aplicada.');
        }
    }

    private function assertSafeTarget(): void
    {
        if (config('demo.enabled') !== true) {
            throw new RuntimeException('A carga de demonstração exige DEMO_ENABLED=true.');
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $database = $connection->getDatabaseName();
        $testingMemory = app()->environment('testing') && $driver === 'sqlite' && $database === ':memory:';
        $productionDemo = app()->environment('production') && $driver === 'mysql' && $database === 'biblioteca_demo';

        if (! $testingMemory && ! $productionDemo) {
            throw new RuntimeException('A carga só pode usar SQLite em memória nos testes ou o MySQL biblioteca_demo em produção.');
        }

        foreach (self::DOMAIN_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Tabela {$table} ausente. Aplique todas as migrations antes da demonstração.");
            }
        }
    }

    /** @return array{admin:string,staff:string,reader:string} */
    private function passwords(): array
    {
        $passwords = [
            'admin' => config('demo.admin_password'),
            'staff' => config('demo.staff_password'),
            'reader' => config('demo.reader_password'),
        ];

        foreach ($passwords as $name => $password) {
            if (! is_string($password) || strlen($password) < 12) {
                throw new RuntimeException("A senha {$name} da demonstração deve vir do ambiente e ter ao menos 12 caracteres.");
            }
        }
        if (count(array_unique($passwords)) !== count($passwords)) {
            throw new RuntimeException('Use senhas distintas para administração, equipe e leitores da demonstração.');
        }

        /** @var array{admin:string,staff:string,reader:string} $passwords */
        return $passwords;
    }

    private function assertEmptyDomain(): void
    {
        foreach (self::DOMAIN_TABLES as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException("O banco contém dados em {$table} sem o marcador da demonstração. Use um banco biblioteca_demo novo.");
            }
        }
    }

    private function assertKnownDataset(AuditLog $marker): void
    {
        $metadata = $marker->metadata ?? [];
        if (($metadata['dataset_version'] ?? null) !== config('demo.dataset_version')) {
            throw new RuntimeException('O banco possui outra versão do cenário de demonstração. Restaure um banco novo em vez de misturar cargas.');
        }
        $accounts = User::query()->whereIn('email', self::ACCOUNT_EMAILS)->count();
        if ($accounts !== count(self::ACCOUNT_EMAILS) || Livro::query()->count() < (int) ($metadata['books'] ?? 0)) {
            throw new RuntimeException('O marcador da demonstração existe, mas o conjunto esperado está incompleto.');
        }
    }

    /**
     * @param  array{admin:string,staff:string,reader:string}  $passwords
     * @return array{admin:User,staff:User,reader:User,queue:User,overdue:User}
     */
    private function createAccounts(array $passwords): array
    {
        return [
            'admin' => $this->createUser('Administração da Demonstração', self::ACCOUNT_EMAILS[0], $passwords['admin'], UserRole::Admin),
            'staff' => $this->createUser('Equipe da Biblioteca', self::ACCOUNT_EMAILS[1], $passwords['staff'], UserRole::Librarian),
            'reader' => $this->createUser('Leitor da Demonstração', self::ACCOUNT_EMAILS[2], $passwords['reader'], UserRole::Reader),
            'queue' => $this->createUser('Leitor da Fila', self::ACCOUNT_EMAILS[3], $passwords['reader'], UserRole::Reader),
            'overdue' => $this->createUser('Leitor com Atraso', self::ACCOUNT_EMAILS[4], $passwords['reader'], UserRole::Reader),
        ];
    }

    private function createUser(string $name, string $email, string $password, UserRole $role): User
    {
        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    /** @return array{0:array<string, Livro>,1:array<string, list<Exemplar>>} */
    private function createCatalog(): array
    {
        $authors = collect([
            ['nome' => 'Marina Alves', 'nacionalidade' => 'Brasileira'],
            ['nome' => 'Rafael Nogueira', 'nacionalidade' => 'Brasileira'],
            ['nome' => 'Helena Costa', 'nacionalidade' => 'Portuguesa'],
            ['nome' => 'Caio Moreira', 'nacionalidade' => 'Brasileira'],
            ['nome' => 'Lia Martins', 'nacionalidade' => 'Angolana'],
        ])->map(fn (array $attributes): Autor => Autor::create($attributes))->values();
        $categories = collect([
            ['nome' => 'Arquitetura', 'descricao' => 'Arquitetura e desenho de software.'],
            ['nome' => 'Operações', 'descricao' => 'Infraestrutura, entrega e observabilidade.'],
            ['nome' => 'Dados', 'descricao' => 'Persistência, consistência e relatórios.'],
            ['nome' => 'Qualidade', 'descricao' => 'Testes, segurança e manutenção.'],
            ['nome' => 'Engenharia', 'descricao' => 'Práticas de desenvolvimento profissional.'],
        ])->map(fn (array $attributes): Categoria => Categoria::create($attributes))->values();

        $specifications = [
            ['key' => 'architecture', 'title' => 'Arquitetura de Sistemas Sustentáveis', 'author' => 0, 'category' => 0, 'copies' => 3],
            ['key' => 'laravel', 'title' => 'Laravel em Projetos Reais', 'author' => 1, 'category' => 4, 'copies' => 3],
            ['key' => 'messaging', 'title' => 'Mensageria e Processamento Assíncrono', 'author' => 2, 'category' => 0, 'copies' => 2],
            ['key' => 'docker', 'title' => 'Contêineres para Equipes', 'author' => 3, 'category' => 1, 'copies' => 2],
            ['key' => 'observability', 'title' => 'Observabilidade Aplicada', 'author' => 4, 'category' => 1, 'copies' => 3],
            ['key' => 'security', 'title' => 'Segurança de APIs', 'author' => 0, 'category' => 3, 'copies' => 2],
            ['key' => 'mysql', 'title' => 'MySQL: Consultas e Concorrência', 'author' => 1, 'category' => 2, 'copies' => 3],
            ['key' => 'testing', 'title' => 'Testes que Protegem o Produto', 'author' => 2, 'category' => 3, 'copies' => 2],
            ['key' => 'domain', 'title' => 'Modelagem de Domínio na Prática', 'author' => 3, 'category' => 0, 'copies' => 2],
            ['key' => 'refactoring', 'title' => 'Refatoração Orientada a Risco', 'author' => 4, 'category' => 4, 'copies' => 2],
            ['key' => 'delivery', 'title' => 'Entrega Contínua sem Atalhos', 'author' => 0, 'category' => 1, 'copies' => 2],
            ['key' => 'git', 'title' => 'Git para Trabalho em Equipe', 'author' => 1, 'category' => 4, 'copies' => 2],
            ['key' => 'last_copy', 'title' => 'A Última Unidade', 'author' => 2, 'category' => 4, 'copies' => 1],
            ['key' => 'overdue_queue', 'title' => 'Prazo e Responsabilidade', 'author' => 3, 'category' => 4, 'copies' => 1],
        ];

        $books = $copies = [];
        foreach ($specifications as $bookNumber => $specification) {
            $key = (string) $specification['key'];
            $book = Livro::create([
                'titulo' => $specification['title'],
                'autor_id' => $authors[(int) $specification['author']]->id,
                'categoria_id' => $categories[(int) $specification['category']]->id,
                'ano_publicacao' => 2012 + $bookNumber,
                'quantidade_total' => 0,
                'quantidade_disponivel' => 0,
                'status' => 'ativo',
                'modo_acervo' => 'exemplares',
            ]);
            $books[$key] = $book;
            $copies[$key] = [];
            for ($copyNumber = 1; $copyNumber <= (int) $specification['copies']; $copyNumber++) {
                $copies[$key][] = Exemplar::create([
                    'livro_id' => $book->id,
                    'codigo_patrimonial' => sprintf('DEMO-%02d-%02d', $bookNumber + 1, $copyNumber),
                    'condicao' => ExemplarCondition::Circulation,
                    'origem' => 'demonstracao',
                    'identificacao_fisica' => true,
                ]);
            }
        }

        $copies['observability'][2]->forceFill([
            'condicao' => ExemplarCondition::Maintenance,
            'motivo_condicao' => 'Revisão preventiva fictícia para demonstrar o inventário.',
        ])->save();
        $copies['delivery'][1]->forceFill([
            'condicao' => ExemplarCondition::Decommissioned,
            'motivo_condicao' => 'Baixa fictícia para demonstrar o histórico do exemplar.',
        ])->save();

        foreach ($books as $book) {
            app(SynchronizeLivroAvailability::class)->execute($book);
        }

        return [$books, $copies];
    }

    /**
     * @param  array{admin:User,staff:User,reader:User,queue:User,overdue:User}  $users
     * @param  array<string, Livro>  $books
     * @param  array<string, list<Exemplar>>  $copies
     */
    private function createCirculationScenarios(array $users, array $books, array $copies): void
    {
        $timezone = (string) config('app.timezone', 'America/Sao_Paulo');
        $now = CarbonImmutable::now($timezone)->startOfDay()->addHours(10);
        $checkout = app(CheckoutExemplar::class);
        $close = app(CloseLoan::class);

        $lost = $this->at($now->subDays(50), fn () => $checkout->execute(
            $users['staff'], $users['overdue']->id, $books['testing']->id, exemplarId: $copies['testing'][0]->id,
        ));
        $this->at($now->subDays(45), fn (): bool => $close->loss(
            $users['staff'], $lost, 'Extravio fictício registrado para a demonstração.',
        ));

        $returned = $this->at($now->subDays(40), fn () => $checkout->execute(
            $users['staff'], $users['queue']->id, $books['git']->id, exemplarId: $copies['git'][0]->id,
        ));
        $this->at($now->subDays(35), fn (): bool => $close->return($users['staff'], $returned));

        $this->at($now->subDays(12), fn () => $checkout->execute(
            $users['staff'], $users['reader']->id, $books['last_copy']->id, exemplarId: $copies['last_copy'][0]->id,
        ));
        $this->at($now, fn () => $checkout->execute(
            $users['staff'], $users['reader']->id, $books['architecture']->id, exemplarId: $copies['architecture'][0]->id,
        ));
        $this->at($now->subDays(20), fn () => $checkout->execute(
            $users['staff'], $users['overdue']->id, $books['overdue_queue']->id, exemplarId: $copies['overdue_queue'][0]->id,
        ));

        $this->at($now, function () use ($users, $books): void {
            app(CreateReservation::class)->execute($users['queue'], $books['last_copy']->id);
            app(CreateReservation::class)->execute($users['reader'], $books['overdue_queue']->id);
            app(CreateReservation::class)->execute($users['queue'], $books['messaging']->id);
        });
    }

    /** @template T
     * @param  Closure(): T  $callback
     * @return T
     */
    private function at(CarbonImmutable $moment, Closure $callback): mixed
    {
        $previous = CarbonImmutable::getTestNow();
        CarbonImmutable::setTestNow($moment);

        try {
            return $callback();
        } finally {
            CarbonImmutable::setTestNow($previous);
        }
    }
}
