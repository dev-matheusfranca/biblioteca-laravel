<?php

namespace Tests\Feature;

use App\Actions\Circulation\CheckoutExemplar;
use App\Actions\Circulation\CreateReservation;
use App\Actions\Circulation\RenewLoan;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PhysicalCatalog;
use Tests\TestCase;

class LoanRenewalTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_reader_renews_once_from_current_due_date_with_snapshot(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 10:00:00 America/Sao_Paulo');
        $staff = User::factory()->librarian()->create();
        $reader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        $loan = app(CheckoutExemplar::class)->execute($staff, $reader->id, $book->id);

        $renewal = app(RenewLoan::class)->execute($reader, $loan);

        $this->assertSame('2026-10-09', $renewal->previous_due_date->toDateString());
        $this->assertSame('2026-10-23', $renewal->new_due_date->toDateString());
        $this->assertSame(1, $loan->fresh()->renewal_count);
        $this->assertSame(1, $renewal->policy_snapshot['version']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('limite de 1');
        app(RenewLoan::class)->execute($reader, $loan->fresh());
    }

    public function test_waiting_reservation_blocks_renewal(): void
    {
        $staff = User::factory()->librarian()->create();
        $borrower = User::factory()->reader()->create();
        $waitingReader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        $loan = app(CheckoutExemplar::class)->execute($staff, $borrower->id, $book->id);
        app(CreateReservation::class)->execute($waitingReader, $book->id);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('reserva elegível aguardando');
        app(RenewLoan::class)->execute($borrower, $loan);
    }

    public function test_available_hold_on_another_copy_does_not_block_renewal(): void
    {
        $staff = User::factory()->librarian()->create();
        $borrower = User::factory()->reader()->create();
        $reservedReader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book(['quantidade_total' => 2, 'quantidade_disponivel' => 2]);
        $loan = app(CheckoutExemplar::class)->execute($staff, $borrower->id, $book->id);
        $reservation = app(CreateReservation::class)->execute($reservedReader, $book->id);
        $this->assertSame('disponivel', $reservation->status->value);

        app(RenewLoan::class)->execute($borrower, $loan);

        $this->assertSame(1, $loan->fresh()->renewal_count);
    }

    public function test_overdue_loan_and_inactive_reader_cannot_renew(): void
    {
        $staff = User::factory()->librarian()->create();
        $reader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        $loan = app(CheckoutExemplar::class)->execute($staff, $reader->id, $book->id);
        $loan->forceFill(['data_devolucao' => today()->subDay()->toDateString()])->save();

        try {
            app(RenewLoan::class)->execute($reader, $loan);
            $this->fail('Empréstimo atrasado deveria ser bloqueado.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('atraso', $exception->getMessage());
        }

        $loan->forceFill(['data_devolucao' => today()->addWeek()->toDateString()])->save();
        $reader->forceFill(['is_active' => false])->save();
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('não pode renovar');
        app(RenewLoan::class)->execute($reader, $loan);
    }
}
