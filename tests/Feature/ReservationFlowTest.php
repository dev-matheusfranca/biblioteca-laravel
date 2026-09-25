<?php

namespace Tests\Feature;

use App\Actions\Circulation\CancelReservation;
use App\Actions\Circulation\CheckoutExemplar;
use App\Actions\Circulation\CloseLoan;
use App\Actions\Circulation\CreateReservation;
use App\Enums\ReservationStatus;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PhysicalCatalog;
use Tests\TestCase;

class ReservationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_return_allocates_fifo_by_id_and_cancellation_reallocates(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 10:00:00 America/Sao_Paulo');
        $staff = User::factory()->librarian()->create();
        $borrower = User::factory()->reader()->create();
        $firstReader = User::factory()->reader()->create();
        $secondReader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        $loan = app(CheckoutExemplar::class)->execute($staff, $borrower->id, $book->id);
        $first = app(CreateReservation::class)->execute($firstReader, $book->id);
        $second = app(CreateReservation::class)->execute($secondReader, $book->id);

        $this->assertSame(ReservationStatus::Waiting, $first->status);
        $this->assertSame(ReservationStatus::Waiting, $second->status);
        app(CloseLoan::class)->return($staff, $loan);

        $this->assertSame(ReservationStatus::Available, $first->fresh()->status);
        $this->assertSame('2026-09-27 10:00:00', $first->fresh()->expira_em->format('Y-m-d H:i:s'));
        $this->assertSame(ReservationStatus::Waiting, $second->fresh()->status);

        app(CancelReservation::class)->execute($firstReader, $first);
        $this->assertSame(ReservationStatus::Cancelled, $first->fresh()->status);
        $this->assertSame(ReservationStatus::Available, $second->fresh()->status);
    }

    public function test_checkout_honors_hold_and_fulfils_reservation(): void
    {
        $staff = User::factory()->librarian()->create();
        $reservedReader = User::factory()->reader()->create();
        $otherReader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        $reservation = app(CreateReservation::class)->execute($reservedReader, $book->id);
        $this->assertSame(ReservationStatus::Available, $reservation->status);

        try {
            app(CheckoutExemplar::class)->execute($staff, $otherReader->id, $book->id);
            $this->fail('A unidade reservada não pode ser retirada por outra pessoa.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Não há exemplar', $exception->getMessage());
        }

        $loan = app(CheckoutExemplar::class)->execute($staff, $reservedReader->id, $book->id);
        $this->assertSame($reservation->active_exemplar_id, $loan->exemplar_id);
        $this->assertSame(ReservationStatus::Fulfilled, $reservation->fresh()->status);
        $this->assertNull($reservation->fresh()->active_key);
    }

    public function test_expired_hold_releases_copy_without_reactivating_reservation(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 10:00:00 America/Sao_Paulo');
        $reader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        $reservation = app(CreateReservation::class)->execute($reader, $book->id);
        $reservation->forceFill(['expira_em' => now()->subMinute()])->save();

        $this->artisan('reservas:expirar')->assertSuccessful();
        $this->artisan('reservas:expirar')->assertSuccessful();

        $this->assertSame(ReservationStatus::Expired, $reservation->fresh()->status);
        $this->assertNull($reservation->fresh()->active_key);
        $this->assertSame(1, $book->fresh()->quantidade_disponivel);
        $this->assertSame(1, AuditLog::query()->where('action', 'reservation.expired')->count());
    }

    public function test_reader_cannot_cancel_another_readers_reservation(): void
    {
        $owner = User::factory()->reader()->create();
        $other = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        $reservation = app(CreateReservation::class)->execute($owner, $book->id);

        $this->actingAs($other)->delete(route('reservas.destroy', $reservation))->assertForbidden();
        $this->assertNotNull($reservation->fresh()->active_key);
    }

    public function test_inactive_reader_hold_is_closed_and_next_eligible_reader_receives_copy(): void
    {
        $staff = User::factory()->librarian()->create();
        $borrower = User::factory()->reader()->create();
        $inactive = User::factory()->reader()->create();
        $eligible = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        $loan = app(CheckoutExemplar::class)->execute($staff, $borrower->id, $book->id);
        $first = app(CreateReservation::class)->execute($inactive, $book->id);
        $second = app(CreateReservation::class)->execute($eligible, $book->id);
        $inactive->forceFill(['is_active' => false])->save();

        app(CloseLoan::class)->return($staff, $loan);

        $this->assertSame(ReservationStatus::Cancelled, $first->fresh()->status);
        $this->assertSame('leitor_indisponivel', $first->fresh()->encerramento_motivo);
        $this->assertSame(ReservationStatus::Available, $second->fresh()->status);
    }
}
