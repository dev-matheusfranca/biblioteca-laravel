<?php

namespace App\Actions\Communication;

use App\Enums\ExemplarCondition;
use App\Enums\OutboxStatus;
use App\Enums\OutboxType;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Mail\CirculationNoticeMail;
use App\Models\CommunicationOutbox;
use App\Models\Exemplar;
use App\Models\Livro;
use App\Models\Locacao;
use App\Models\PortalNotice;
use App\Models\Reserva;
use App\Models\User;
use App\Services\CirculationEligibility;
use App\Services\CurrentCirculationPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class DeliverOutboxEvent
{
    /** @var list<int> */
    private const BACKOFF_SECONDS = [5, 30, 120];

    public function __construct(
        private readonly CurrentCirculationPolicy $policies,
        private readonly CirculationEligibility $eligibility,
    ) {}

    public function execute(int $outboxId, ?string $deliveryToken = null): void
    {
        $deliveryToken ??= (string) Str::uuid();
        $claim = $this->claim($outboxId, $deliveryToken);
        if (! $claim) {
            return;
        }

        try {
            match ($claim['type']) {
                OutboxType::ReservationAvailable => $this->deliverReservation($outboxId, $claim),
                OutboxType::LoanDueSoon, OutboxType::LoanOverdue => $this->deliverLoan($outboxId, $claim),
            };
        } catch (Throwable $exception) {
            $this->releaseAfterFailure($outboxId, $claim['lease_token']);
            Log::warning('Communication delivery attempt failed', [
                'outbox_id' => $outboxId,
                'correlation_id' => $claim['correlation_id'],
                'error_code' => 'delivery_failed',
            ]);

            throw $exception;
        }
    }

    public function markFailed(int $outboxId, string $deliveryToken): void
    {
        DB::transaction(function () use ($deliveryToken, $outboxId) {
            $event = CommunicationOutbox::query()->lockForUpdate()->find($outboxId);
            if (! $event || $event->status->isFinal() || $event->lease_token !== $deliveryToken) {
                return;
            }
            $event->forceFill([
                'status' => OutboxStatus::Failed,
                'lease_token' => null,
                'leased_at' => null,
                'next_attempt_at' => null,
                'completed_at' => now(),
                'error_code' => 'delivery_failed',
            ])->save();
        });
    }

    /** @return array{type:OutboxType,payload:array<string,mixed>,lease_token:string,correlation_id:string}|null */
    private function claim(int $outboxId, string $deliveryToken): ?array
    {
        return DB::transaction(function () use ($outboxId, $deliveryToken) {
            $event = CommunicationOutbox::query()->lockForUpdate()->find($outboxId);
            if (! $event || $event->status->isFinal()) {
                return null;
            }
            if ($event->status === OutboxStatus::Processing && $event->leased_at?->isAfter(now()->subMinutes(2))) {
                return null;
            }
            if ($event->status === OutboxStatus::Pending && $event->next_attempt_at?->isFuture()) {
                return null;
            }
            if ($event->attempts >= 3) {
                $event->forceFill([
                    'status' => OutboxStatus::Failed,
                    'lease_token' => null,
                    'leased_at' => null,
                    'next_attempt_at' => null,
                    'completed_at' => now(),
                    'error_code' => 'delivery_failed',
                ])->save();

                return null;
            }

            $event->forceFill([
                'status' => OutboxStatus::Processing,
                'attempts' => $event->attempts + 1,
                'lease_token' => $deliveryToken,
                'leased_at' => now(),
                'next_attempt_at' => null,
                'error_code' => null,
            ])->save();

            return [
                'type' => $event->type,
                'payload' => $event->payload,
                'lease_token' => $deliveryToken,
                'correlation_id' => $event->correlation_id,
            ];
        }, 3);
    }

    /** @param array{type:OutboxType,payload:array<string,mixed>,lease_token:string,correlation_id:string} $claim */
    private function deliverReservation(int $outboxId, array $claim): void
    {
        DB::transaction(function () use ($outboxId, $claim) {
            $user = User::query()->lockForUpdate()->find((int) $claim['payload']['user_id']);
            $book = Livro::query()->lockForUpdate()->find((int) $claim['payload']['book_id']);
            $reservation = Reserva::query()->lockForUpdate()->find((int) $claim['payload']['reservation_id']);
            $copy = $reservation?->active_exemplar_id
                ? Exemplar::query()->lockForUpdate()->find($reservation->active_exemplar_id)
                : null;
            $event = CommunicationOutbox::query()->lockForUpdate()->find($outboxId);
            if (! $event || $event->status !== OutboxStatus::Processing || $event->lease_token !== $claim['lease_token']) {
                return;
            }
            $valid = $user && $book && $reservation
                && $user->isActive() && $user->role === UserRole::Reader
                && $book->status === 'ativo' && $book->usaExemplares()
                && $reservation->usuario_id === $user->id
                && $reservation->status === ReservationStatus::Available
                && $reservation->active_exemplar_id !== null
                && $copy && $copy->livro_id === $book->id
                && $copy->identificacao_fisica && $copy->condicao === ExemplarCondition::Circulation
                && ! $copy->locacaoAtiva()->exists()
                && $reservation->availability_version === (int) $claim['payload']['availability_version']
                && $reservation->expira_em?->isFuture()
                && $this->eligibility->readerCanReceiveHold($user, $this->policies->get());
            if (! $valid) {
                $this->cancelLocked($event);

                return;
            }

            $expiresAt = $reservation->expira_em->timezone('America/Sao_Paulo')->format('d/m/Y H:i');
            $title = 'Reserva disponível para retirada';
            $body = "A reserva de {$book->titulo} está disponível para retirada até {$expiresAt}.";
            $this->sendLocked($event, $user, $title, $body, route('portal.index'));
        }, 3);
    }

    /** @param array{type:OutboxType,payload:array<string,mixed>,lease_token:string,correlation_id:string} $claim */
    private function deliverLoan(int $outboxId, array $claim): void
    {
        DB::transaction(function () use ($outboxId, $claim) {
            $user = User::query()->lockForUpdate()->find((int) $claim['payload']['user_id']);
            $book = Livro::query()->lockForUpdate()->find((int) $claim['payload']['book_id']);
            $loan = Locacao::query()->lockForUpdate()->find((int) $claim['payload']['loan_id']);
            $event = CommunicationOutbox::query()->lockForUpdate()->find($outboxId);
            if (! $event || $event->status !== OutboxStatus::Processing || $event->lease_token !== $claim['lease_token']) {
                return;
            }
            $dueDate = (string) $claim['payload']['due_date'];
            $today = CarbonImmutable::now('America/Sao_Paulo')->startOfDay();
            $due = CarbonImmutable::parse($dueDate, 'America/Sao_Paulo')->startOfDay();
            $inWindow = $event->type === OutboxType::LoanDueSoon
                ? $due->betweenIncluded($today, $today->addDays(2))
                : $due->lte($today->subDay());
            $valid = $user && $book && $loan
                && $user->isActive() && $user->role === UserRole::Reader
                && $loan->usuario_id === $user->id
                && $loan->encerrado_em === null
                && $loan->data_devolucao === $dueDate
                && $inWindow;
            if (! $valid) {
                $this->cancelLocked($event);

                return;
            }

            $formattedDueDate = CarbonImmutable::parse($dueDate, 'America/Sao_Paulo')->format('d/m/Y');
            if ($event->type === OutboxType::LoanDueSoon) {
                $title = "Devolução prevista para {$formattedDueDate}";
                $body = "O empréstimo de {$book->titulo} vence em {$formattedDueDate}.";
            } else {
                $title = "Empréstimo vencido em {$formattedDueDate}";
                $body = "O empréstimo de {$book->titulo} venceu em {$formattedDueDate}. Procure a equipe para regularizar.";
            }
            $this->sendLocked($event, $user, $title, $body, route('portal.emprestimos.show', $loan));
        }, 3);
    }

    private function sendLocked(CommunicationOutbox $event, User $user, string $title, string $body, string $actionUrl): void
    {
        PortalNotice::query()->firstOrCreate(
            ['outbox_id' => $event->id],
            ['usuario_id' => $user->id, 'type' => $event->type->value, 'title' => $title, 'body' => $body],
        );
        if ($event->mail_completed_at === null) {
            Mail::to($user)->send(new CirculationNoticeMail($title, $body, $actionUrl));
        }
        $event->forceFill([
            'status' => OutboxStatus::Sent,
            'portal_completed_at' => $event->portal_completed_at ?? now(),
            'mail_completed_at' => $event->mail_completed_at ?? now(),
            'completed_at' => now(),
            'lease_token' => null,
            'leased_at' => null,
            'next_attempt_at' => null,
            'error_code' => null,
        ])->save();
    }

    private function cancelLocked(CommunicationOutbox $event): void
    {
        $event->forceFill([
            'status' => OutboxStatus::Cancelled,
            'completed_at' => now(),
            'lease_token' => null,
            'leased_at' => null,
            'next_attempt_at' => null,
            'error_code' => 'domain_state_changed',
        ])->save();
        $event->portalNotice()->whereNull('cancelled_at')->update(['cancelled_at' => now()]);
    }

    private function releaseAfterFailure(int $outboxId, string $leaseToken): void
    {
        DB::transaction(function () use ($outboxId, $leaseToken) {
            $event = CommunicationOutbox::query()->lockForUpdate()->find($outboxId);
            if (! $event || $event->status !== OutboxStatus::Processing || $event->lease_token !== $leaseToken) {
                return;
            }
            $delay = self::BACKOFF_SECONDS[min(max($event->attempts - 1, 0), count(self::BACKOFF_SECONDS) - 1)];
            $event->forceFill([
                'status' => OutboxStatus::Pending,
                'lease_token' => $leaseToken,
                'leased_at' => now(),
                'next_attempt_at' => now()->addSeconds($delay),
                'error_code' => 'delivery_failed',
            ])->save();
        });
    }
}
