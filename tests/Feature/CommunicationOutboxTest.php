<?php

namespace Tests\Feature;

use App\Actions\Circulation\AllocateReservationsForBook;
use App\Actions\Circulation\CancelReservation;
use App\Actions\Circulation\CheckoutExemplar;
use App\Actions\Circulation\CloseLoan;
use App\Actions\Circulation\CreateReservation;
use App\Actions\Communication\DeliverOutboxEvent;
use App\Actions\Communication\PrepareCommunicationEvents;
use App\Actions\Communication\PublishOutbox;
use App\Enums\OutboxStatus;
use App\Jobs\DeliverCommunication;
use App\Mail\CirculationNoticeMail;
use App\Models\CommunicationOutbox;
use App\Models\PortalNotice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\PhysicalCatalog;
use Tests\TestCase;

class CommunicationOutboxTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_reservation_event_is_durable_only_when_domain_transaction_commits(): void
    {
        $staff = User::factory()->librarian()->create();
        $borrower = User::factory()->reader()->create();
        $waitingReader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        $loan = app(CheckoutExemplar::class)->execute($staff, $borrower->id, $book->id);
        app(CreateReservation::class)->execute($waitingReader, $book->id);

        try {
            DB::transaction(function () use ($staff, $loan) {
                app(CloseLoan::class)->return($staff, $loan);
                throw new RuntimeException('rollback esperado');
            });
        } catch (RuntimeException) {
        }

        $this->assertDatabaseCount('communication_outbox', 0);
        $this->assertNull($loan->fresh()->encerrado_em);

        app(CloseLoan::class)->return($staff, $loan);
        $event = CommunicationOutbox::query()->sole();
        $this->assertSame('reservation.available', $event->type->value);
        $this->assertSame(1, $event->payload['availability_version']);
    }

    public function test_duplicate_delivery_creates_one_portal_notice_and_one_email(): void
    {
        Mail::fake();
        $reader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        app(CreateReservation::class)->execute($reader, $book->id);
        $event = CommunicationOutbox::query()->sole();

        app(DeliverOutboxEvent::class)->execute($event->id);
        app(DeliverOutboxEvent::class)->execute($event->id);

        $this->assertSame(OutboxStatus::Sent, $event->fresh()->status);
        $this->assertDatabaseCount('portal_notices', 1);
        Mail::assertSent(CirculationNoticeMail::class, 1);
    }

    public function test_cancelled_hold_cancels_pending_event_and_sends_nothing(): void
    {
        Mail::fake();
        $reader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        $reservation = app(CreateReservation::class)->execute($reader, $book->id);
        $event = CommunicationOutbox::query()->sole();

        app(CancelReservation::class)->execute($reader, $reservation);
        app(DeliverOutboxEvent::class)->execute($event->id);

        $this->assertSame(OutboxStatus::Cancelled, $event->fresh()->status);
        $this->assertDatabaseCount('portal_notices', 0);
        Mail::assertNothingSent();
    }

    public function test_scanner_is_idempotent_and_stale_loan_state_is_suppressed(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 09:00:00 America/Sao_Paulo');
        Mail::fake();
        $reader = User::factory()->reader()->create();
        [, , $dueBook] = PhysicalCatalog::book();
        [, , $overdueBook] = PhysicalCatalog::book();
        $dueLoan = PhysicalCatalog::loan($reader, $dueBook, ['data_devolucao' => '2026-09-27']);
        $overdueLoan = PhysicalCatalog::loan($reader, $overdueBook, ['data_devolucao' => '2026-09-24']);

        $this->assertSame(2, app(PrepareCommunicationEvents::class)->execute());
        $this->assertSame(0, app(PrepareCommunicationEvents::class)->execute());
        $this->assertDatabaseCount('communication_outbox', 2);

        $dueLoan->forceFill(['data_devolucao' => '2026-09-28'])->save();
        $overdueLoan->forceFill(['encerrado_em' => now(), 'encerramento_motivo' => 'devolucao', 'status' => 'devolvida'])->save();
        foreach (CommunicationOutbox::all() as $event) {
            app(DeliverOutboxEvent::class)->execute($event->id);
        }

        $this->assertSame(2, CommunicationOutbox::query()->where('status', OutboxStatus::Cancelled->value)->count());
        $this->assertDatabaseCount('portal_notices', 0);
        Mail::assertNothingSent();
    }

    public function test_due_notice_uses_absolute_date_in_both_channels(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 09:00:00 America/Sao_Paulo');
        Mail::fake();
        $reader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        PhysicalCatalog::loan($reader, $book, ['data_devolucao' => '2026-09-27']);
        app(PrepareCommunicationEvents::class)->execute();
        $event = CommunicationOutbox::query()->sole();

        app(DeliverOutboxEvent::class)->execute($event->id);

        $notice = PortalNotice::query()->sole();
        $this->assertStringContainsString('27/09/2026', $notice->title.$notice->body);
        Mail::assertSent(CirculationNoticeMail::class, fn (CirculationNoticeMail $mail): bool => str_contains($mail->title.$mail->body, '27/09/2026'));
    }

    public function test_delivery_failure_is_sanitized_and_admin_can_manually_replay(): void
    {
        $reader = User::factory()->reader()->create();
        $admin = User::factory()->admin()->create();
        [, , $book] = PhysicalCatalog::book();
        app(CreateReservation::class)->execute($reader, $book->id);
        $event = CommunicationOutbox::query()->sole();
        Mail::shouldReceive('to')->times(3)->andThrow(new RuntimeException('smtp-secret-detail'));
        $job = new DeliverCommunication($event->id, $event->correlation_id);

        foreach (range(1, 3) as $attempt) {
            try {
                app(DeliverOutboxEvent::class)->execute($event->id, $job->deliveryToken);
            } catch (RuntimeException) {
            }
            CommunicationOutbox::query()->whereKey($event->id)->update(['next_attempt_at' => null]);
        }
        $job->failed(new RuntimeException('smtp-secret-detail'));

        $event->refresh();
        $this->assertSame(3, $event->attempts);
        $this->assertSame(OutboxStatus::Failed, $event->status);
        $this->assertSame('delivery_failed', $event->error_code);
        $this->assertStringNotContainsString('secret', (string) $event->error_code);

        Queue::fake();
        $this->actingAs($admin)->post(route('operacao.comunicacoes.reprocessar', $event))->assertSessionHasNoErrors();
        $this->assertSame(OutboxStatus::Pending, $event->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'communication.reprocessed', 'actor_id' => $admin->id]);
        Queue::assertPushed(DeliverCommunication::class, 1);
    }

    public function test_persisted_attempt_limit_blocks_a_fourth_delivery_and_an_obsolete_callback_cannot_fail_a_new_lease(): void
    {
        $reader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        app(CreateReservation::class)->execute($reader, $book->id);
        $event = CommunicationOutbox::query()->sole();
        Mail::shouldReceive('to')->times(3)->andThrow(new RuntimeException('smtp unavailable'));
        $obsoleteJob = new DeliverCommunication($event->id, $event->correlation_id);

        try {
            app(DeliverOutboxEvent::class)->execute($event->id, $obsoleteJob->deliveryToken);
        } catch (RuntimeException) {
        }
        CommunicationOutbox::query()->whereKey($event->id)->update(['next_attempt_at' => null]);

        $currentJob = new DeliverCommunication($event->id, $event->correlation_id);
        try {
            app(DeliverOutboxEvent::class)->execute($event->id, $currentJob->deliveryToken);
        } catch (RuntimeException) {
        }
        $obsoleteJob->failed(new RuntimeException('old worker failed late'));
        $event->refresh();
        $this->assertSame(OutboxStatus::Pending, $event->status);
        $this->assertSame($currentJob->deliveryToken, $event->lease_token);
        CommunicationOutbox::query()->whereKey($event->id)->update(['next_attempt_at' => null]);

        try {
            app(DeliverOutboxEvent::class)->execute($event->id, $currentJob->deliveryToken);
        } catch (RuntimeException) {
        }
        CommunicationOutbox::query()->whereKey($event->id)->update(['next_attempt_at' => null]);
        app(DeliverOutboxEvent::class)->execute($event->id, (string) Str::uuid());

        $event->refresh();
        $this->assertSame(3, $event->attempts);
        $this->assertSame(OutboxStatus::Failed, $event->status);
    }

    public function test_reminders_are_cancelled_when_their_delivery_window_has_passed(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 09:00:00 America/Sao_Paulo');
        Mail::fake();
        $reader = User::factory()->reader()->create();
        [, , $dueBook] = PhysicalCatalog::book();
        [, , $overdueBook] = PhysicalCatalog::book();
        PhysicalCatalog::loan($reader, $dueBook, ['data_devolucao' => '2026-09-26']);
        PhysicalCatalog::loan($reader, $overdueBook, ['data_devolucao' => '2026-09-24']);
        app(PrepareCommunicationEvents::class)->execute();
        $dueSoon = CommunicationOutbox::query()->where('type', 'loan.due_soon')->sole();
        $overdue = CommunicationOutbox::query()->where('type', 'loan.overdue')->sole();

        CarbonImmutable::setTestNow('2026-09-27 09:00:00 America/Sao_Paulo');
        app(DeliverOutboxEvent::class)->execute($dueSoon->id);
        CarbonImmutable::setTestNow('2026-09-24 09:00:00 America/Sao_Paulo');
        app(DeliverOutboxEvent::class)->execute($overdue->id);

        $this->assertSame(OutboxStatus::Cancelled, $dueSoon->fresh()->status);
        $this->assertSame(OutboxStatus::Cancelled, $overdue->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_relay_throttles_duplicates_and_recovers_a_stale_lease(): void
    {
        Queue::fake();
        $reader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        app(CreateReservation::class)->execute($reader, $book->id);
        $event = CommunicationOutbox::query()->sole();

        $this->assertSame(1, app(PublishOutbox::class)->execute());
        $this->assertSame(0, app(PublishOutbox::class)->execute());
        Queue::assertPushed(DeliverCommunication::class, 1);

        $event->forceFill([
            'status' => OutboxStatus::Processing,
            'leased_at' => now()->subMinutes(3),
            'last_enqueued_at' => now()->subMinutes(3),
        ])->save();
        $this->assertSame(1, app(PublishOutbox::class)->execute());
        Queue::assertPushed(DeliverCommunication::class, 2);
    }

    public function test_broker_failure_preserves_the_outbox_intention_for_a_later_publish(): void
    {
        $reader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        app(CreateReservation::class)->execute($reader, $book->id);
        $event = CommunicationOutbox::query()->sole();
        $eventKey = $event->event_key;
        $dispatcher = app(Dispatcher::class);
        $failedDispatcher = \Mockery::mock(Dispatcher::class);
        $failedDispatcher->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('redis connection details'));
        $this->app->instance(Dispatcher::class, $failedDispatcher);

        $this->assertFalse(app(PublishOutbox::class)->publishOne($event->id));

        $event->refresh();
        $this->assertSame(OutboxStatus::Pending, $event->status);
        $this->assertSame($eventKey, $event->event_key);
        $this->assertSame('queue_dispatch_failed', $event->error_code);
        $this->assertNotNull($event->next_attempt_at);

        $this->app->instance(Dispatcher::class, $dispatcher);
        Queue::fake();
        $event->forceFill(['next_attempt_at' => null])->save();
        $this->assertTrue(app(PublishOutbox::class)->publishOne($event->id));
        $this->assertDatabaseCount('communication_outbox', 1);
        Queue::assertPushed(DeliverCommunication::class, 1);
    }

    public function test_requeued_reservation_uses_a_new_availability_version(): void
    {
        $staff = User::factory()->librarian()->create();
        $reader = User::factory()->reader()->create();
        [, , $reservedBook] = PhysicalCatalog::book();
        $reservation = app(CreateReservation::class)->execute($reader, $reservedBook->id);
        $this->assertSame(1, $reservation->availability_version);

        $loans = collect(range(1, 3))->map(function () use ($staff, $reader) {
            [, , $book] = PhysicalCatalog::book();

            return app(CheckoutExemplar::class)->execute($staff, $reader->id, $book->id);
        });
        app(AllocateReservationsForBook::class)->execute($reservedBook->id);
        $this->assertSame('aguardando', $reservation->fresh()->status->value);
        $this->assertSame(OutboxStatus::Cancelled, CommunicationOutbox::query()->where('event_key', "reservation:{$reservation->id}:available:1")->firstOrFail()->status);

        app(CloseLoan::class)->return($staff, $loans->first());
        app(AllocateReservationsForBook::class)->execute($reservedBook->id);

        $this->assertSame(2, $reservation->fresh()->availability_version);
        $this->assertDatabaseHas('communication_outbox', ['event_key' => "reservation:{$reservation->id}:available:2", 'status' => OutboxStatus::Pending->value]);
    }
}
