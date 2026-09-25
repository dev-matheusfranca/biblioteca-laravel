<?php

namespace Tests\Feature;

use App\Actions\Circulation\RenewLoan;
use App\Enums\OutboxStatus;
use App\Enums\OutboxType;
use App\Models\AuditLog;
use App\Models\CommunicationOutbox;
use App\Models\Livro;
use App\Models\Locacao;
use App\Models\Reserva;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'sqlite' || DB::getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Os seis cenários do DemoSeeder usam SQLite em memória; o gate MySQL roda somente no banco local isolado biblioteca_demo.');
        }
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-25 12:00:00', 'America/Sao_Paulo'));
        config([
            'demo.enabled' => true,
            'demo.admin_password' => 'Admin-demo-2026!',
            'demo.staff_password' => 'Equipe-demo-2026!',
            'demo.reader_password' => 'Leitor-demo-2026!',
        ]);
        Mail::fake();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_demo_seed_creates_a_safe_portfolio_scenario_without_delivering_messages(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('autores', 5);
        $this->assertDatabaseCount('categorias', 5);
        $this->assertDatabaseCount('livros', 14);
        $this->assertDatabaseCount('exemplares', 30);
        $this->assertDatabaseCount('locacoes', 5);
        $this->assertDatabaseCount('reservas', 3);
        $this->assertDatabaseHas('users', ['email' => 'admin@biblioteca.example.test', 'role' => 'admin', 'is_active' => true]);
        $this->assertDatabaseHas('users', ['email' => 'equipe@biblioteca.example.test', 'role' => 'bibliotecario', 'is_active' => true]);
        $this->assertDatabaseHas('users', ['email' => 'leitor@biblioteca.example.test', 'role' => 'leitor', 'is_active' => true]);
        $this->assertTrue(Hash::check('Admin-demo-2026!', User::query()->where('email', 'admin@biblioteca.example.test')->sole()->password));

        $reader = User::query()->where('email', 'leitor@biblioteca.example.test')->sole();
        $queueReader = User::query()->where('email', 'fila@biblioteca.example.test')->sole();
        $overdueReader = User::query()->where('email', 'atraso@biblioteca.example.test')->sole();
        $this->assertSame(2, Locacao::query()->where('usuario_id', $reader->id)->whereNull('encerrado_em')->count());
        $renewable = Locacao::query()->where('usuario_id', $reader->id)
            ->whereHas('livro', fn ($query) => $query->where('titulo', 'Arquitetura de Sistemas Sustentáveis'))
            ->sole();
        $this->assertNull(app(RenewLoan::class)->blockReason($reader, $renewable));
        $this->assertDatabaseHas('locacoes', [
            'usuario_id' => $overdueReader->id,
            'data_devolucao' => '2026-09-19',
            'encerrado_em' => null,
        ]);
        $this->assertDatabaseHas('reservas', ['usuario_id' => $reader->id, 'status' => 'aguardando']);
        $this->assertDatabaseHas('reservas', ['usuario_id' => $queueReader->id, 'status' => 'aguardando']);
        $this->assertDatabaseHas('reservas', ['usuario_id' => $queueReader->id, 'status' => 'disponivel', 'availability_version' => 1]);

        $this->assertDatabaseHas('exemplares', ['condicao' => 'manutencao']);
        $this->assertDatabaseHas('exemplares', ['condicao' => 'baixado']);
        $this->assertDatabaseHas('exemplares', ['condicao' => 'extraviado']);
        $this->assertSame(23, (int) Livro::query()->sum('quantidade_disponivel'));

        $this->assertDatabaseCount('communication_outbox', 3);
        $this->assertSame(1, CommunicationOutbox::query()->where('type', OutboxType::ReservationAvailable)->count());
        $this->assertSame(1, CommunicationOutbox::query()->where('type', OutboxType::LoanDueSoon)->count());
        $this->assertSame(1, CommunicationOutbox::query()->where('type', OutboxType::LoanOverdue)->count());
        $this->assertSame(3, CommunicationOutbox::query()->where('status', OutboxStatus::Pending)->count());
        $this->assertDatabaseCount('portal_notices', 0);
        Mail::assertNothingSent();

        $marker = AuditLog::query()->where('action', 'demo.initialized')->sole();
        $this->assertSame('portfolio-v1', $marker->metadata['dataset_version']);
        $this->assertSame(30, $marker->metadata['copies']);
    }

    public function test_reapplying_the_same_demo_seed_is_a_no_op(): void
    {
        $this->seed(DemoSeeder::class);
        $before = [
            'users' => User::query()->count(),
            'books' => Livro::query()->count(),
            'loans' => Locacao::query()->count(),
            'reservations' => Reserva::query()->count(),
            'outbox' => CommunicationOutbox::query()->count(),
            'audit' => AuditLog::query()->count(),
            'password' => User::query()->where('email', 'admin@biblioteca.example.test')->value('password'),
        ];

        $this->seed(DemoSeeder::class);

        $this->assertSame($before['users'], User::query()->count());
        $this->assertSame($before['books'], Livro::query()->count());
        $this->assertSame($before['loans'], Locacao::query()->count());
        $this->assertSame($before['reservations'], Reserva::query()->count());
        $this->assertSame($before['outbox'], CommunicationOutbox::query()->count());
        $this->assertSame($before['audit'], AuditLog::query()->count());
        $this->assertSame($before['password'], User::query()->where('email', 'admin@biblioteca.example.test')->value('password'));
        $this->assertSame(1, AuditLog::query()->where('action', 'demo.initialized')->count());
    }

    public function test_seed_aborts_on_foreign_data_without_changing_it(): void
    {
        User::create([
            'name' => 'Conta anterior',
            'email' => 'anterior@example.test',
            'password' => 'senha-anterior-segura',
        ]);

        try {
            $this->seed(DemoSeeder::class);
            $this->fail('A carga deveria recusar um banco com dados alheios.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('sem o marcador', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['email' => 'anterior@example.test']);
        $this->assertDatabaseMissing('users', ['email' => 'admin@biblioteca.example.test']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'demo.initialized']);
    }

    public function test_seed_requires_explicit_enablement_external_passwords_and_a_complete_marker(): void
    {
        config(['demo.enabled' => false]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DEMO_ENABLED=true');
        $this->seed(DemoSeeder::class);
    }

    public function test_seed_rejects_missing_password_before_writing_data(): void
    {
        config(['demo.reader_password' => null]);

        try {
            $this->seed(DemoSeeder::class);
            $this->fail('A carga deveria exigir as senhas externas.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('deve vir do ambiente', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('livros', 0);
    }

    public function test_seed_rejects_an_incomplete_marked_dataset(): void
    {
        $this->seed(DemoSeeder::class);
        DB::table('users')->where('email', 'admin@biblioteca.example.test')->delete();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('conjunto esperado está incompleto');
        $this->seed(DemoSeeder::class);
    }
}
