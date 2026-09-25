<?php

namespace Tests\Feature;

use App\Actions\Circulation\CheckoutExemplar;
use App\Models\CirculationPolicy;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PhysicalCatalog;
use Tests\TestCase;

class CirculationPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_checkout_derives_due_date_and_preserves_policy_snapshot(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 10:00:00 America/Sao_Paulo');
        $staff = User::factory()->librarian()->create();
        $reader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();

        $loan = app(CheckoutExemplar::class)->execute($staff, $reader->id, $book->id, '2099-12-31');

        $this->assertSame('2026-10-09', $loan->data_devolucao);
        $this->assertSame(1, $loan->policy_snapshot['version']);
        $this->assertSame(14, $loan->policy_snapshot['loan_days']);
        $this->assertSame('America/Sao_Paulo', $loan->policy_snapshot['timezone']);
    }

    public function test_checkout_enforces_global_open_loan_limit(): void
    {
        $staff = User::factory()->librarian()->create();
        $reader = User::factory()->reader()->create();
        $books = collect(range(1, 4))->map(fn () => PhysicalCatalog::book()[2]);

        foreach ($books->take(3) as $book) {
            app(CheckoutExemplar::class)->execute($staff, $reader->id, $book->id);
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('limite de 3');
        app(CheckoutExemplar::class)->execute($staff, $reader->id, $books->last()->id);
    }

    public function test_admin_updates_policy_from_browser_strings_and_history_is_immutable(): void
    {
        $admin = User::factory()->admin()->create();
        $payload = [
            'loan_days' => '21',
            'max_open_loans' => '4',
            'max_renewals' => '2',
            'renewal_days' => '7',
            'pickup_hours' => '72',
            'blocks_overdue' => '0',
            'timezone' => 'America/Sao_Paulo',
            'reason' => 'Ajuste aprovado para o atendimento.',
            'expected_version' => '1',
        ];

        $this->actingAs($admin)->patch(route('configuracoes.circulacao.update'), $payload)
            ->assertRedirect(route('configuracoes.circulacao.edit'));

        $this->assertDatabaseCount('politicas_circulacao', 2);
        $this->assertDatabaseHas('politicas_circulacao', ['version' => 1, 'active_key' => null, 'loan_days' => 14]);
        $this->assertDatabaseHas('politicas_circulacao', ['version' => 2, 'active_key' => 'current', 'loan_days' => 21, 'blocks_overdue' => false]);

        $this->actingAs($admin)->patch(route('configuracoes.circulacao.update'), $payload)
            ->assertSessionHasErrors('expected_version');
        $this->assertDatabaseCount('politicas_circulacao', 2);
    }

    public function test_non_admin_cannot_edit_policy(): void
    {
        $this->actingAs(User::factory()->librarian()->create())
            ->get(route('configuracoes.circulacao.edit'))
            ->assertForbidden();
        $this->assertSame(1, CirculationPolicy::query()->count());
    }
}
