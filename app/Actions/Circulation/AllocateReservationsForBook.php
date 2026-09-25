<?php

namespace App\Actions\Circulation;

use App\Actions\Communication\CancelReservationAvailabilityCommunication;
use App\Actions\Communication\RecordOutboxEvent;
use App\Actions\Inventory\SynchronizeLivroAvailability;
use App\Enums\ExemplarCondition;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Exemplar;
use App\Models\Livro;
use App\Models\Reserva;
use App\Services\CirculationEligibility;
use App\Services\CurrentCirculationPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AllocateReservationsForBook
{
    public function __construct(
        private readonly CurrentCirculationPolicy $policies,
        private readonly CirculationEligibility $eligibility,
        private readonly RecordOutboxEvent $outbox,
        private readonly CancelReservationAvailabilityCommunication $cancelCommunication,
    ) {}

    public function execute(int $bookId): void
    {
        DB::transaction(function () use ($bookId) {
            $book = Livro::query()->lockForUpdate()->findOrFail($bookId);
            $this->executeLocked($book);
        }, 3);
    }

    public function executeLocked(Livro $book): void
    {
        $policy = $this->policies->get();
        $now = CarbonImmutable::now($policy->timezone);

        $active = Reserva::query()
            ->where('livro_id', $book->id)
            ->whereNotNull('active_key')
            ->orderBy('id')
            ->with(['usuario', 'exemplar'])
            ->lockForUpdate()
            ->get();

        if (! $book->usaExemplares() || $book->status !== 'ativo') {
            foreach ($active as $reservation) {
                $this->close($reservation, ReservationStatus::Cancelled, 'titulo_indisponivel', $now);
            }
            app(SynchronizeLivroAvailability::class)->execute($book);

            return;
        }

        foreach ($active->where('status', ReservationStatus::Available) as $reservation) {
            if ($reservation->expira_em?->lte($now)) {
                $this->close($reservation, ReservationStatus::Expired, 'prazo_retirada_expirado', $now);

                continue;
            }
            $reader = $reservation->usuario;
            if (! $reader || ! $reader->isActive() || $reader->role !== UserRole::Reader) {
                $this->close($reservation, ReservationStatus::Cancelled, 'leitor_indisponivel', $now);

                continue;
            }
            $copy = $reservation->exemplar;
            $copyUnavailable = ! $copy
                || ! $copy->identificacao_fisica
                || $copy->condicao !== ExemplarCondition::Circulation
                || $copy->locacaoAtiva()->exists();
            if ($copyUnavailable || ! $this->eligibility->readerCanReceiveHold($reader, $policy)) {
                $this->cancelCommunication->execute($reservation);
                $reservation->forceFill([
                    'status' => ReservationStatus::Waiting,
                    'exemplar_id' => null,
                    'active_exemplar_id' => null,
                    'disponivel_em' => null,
                    'expira_em' => null,
                ])->save();
                AuditLog::create([
                    'action' => 'reservation.requeued',
                    'subject_id' => $reservation->usuario_id,
                    'metadata' => ['reserva_id' => $reservation->id, 'livro_id' => $book->id],
                ]);
            }
        }

        $copies = Exemplar::query()
            ->where('livro_id', $book->id)
            ->where('condicao', ExemplarCondition::Circulation->value)
            ->where('identificacao_fisica', true)
            ->whereDoesntHave('locacaoAtiva')
            ->whereDoesntHave('reservaAtiva')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($copies->isNotEmpty()) {
            $waiting = Reserva::query()
                ->where('livro_id', $book->id)
                ->where('status', ReservationStatus::Waiting->value)
                ->whereNotNull('active_key')
                ->with('usuario')
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($waiting as $reservation) {
                $reader = $reservation->usuario;
                if (! $reader || ! $reader->isActive() || $reader->role !== UserRole::Reader) {
                    $this->close($reservation, ReservationStatus::Cancelled, 'leitor_indisponivel', $now);

                    continue;
                }
                if (! $this->eligibility->readerCanReceiveHold($reader, $policy)) {
                    continue;
                }
                $copy = $copies->shift();
                if (! $copy) {
                    break;
                }
                $pickupHours = max(1, (int) ($reservation->policy_snapshot['pickup_hours'] ?? $policy->pickup_hours));
                $reservation->forceFill([
                    'status' => ReservationStatus::Available,
                    'availability_version' => $reservation->availability_version + 1,
                    'exemplar_id' => $copy->id,
                    'active_exemplar_id' => $copy->id,
                    'disponivel_em' => $now,
                    'expira_em' => $now->addHours($pickupHours),
                ])->save();
                $this->outbox->reservationAvailable($reservation);
                AuditLog::create([
                    'action' => 'reservation.available',
                    'subject_id' => $reservation->usuario_id,
                    'metadata' => ['reserva_id' => $reservation->id, 'livro_id' => $book->id, 'exemplar_id' => $copy->id, 'expires_at' => $reservation->expira_em?->toIso8601String()],
                ]);
            }
        }

        app(SynchronizeLivroAvailability::class)->execute($book);
    }

    private function close(Reserva $reservation, ReservationStatus $status, string $reason, CarbonImmutable $now): void
    {
        $this->cancelCommunication->execute($reservation);
        $reservation->forceFill([
            'status' => $status,
            'active_key' => null,
            'active_exemplar_id' => null,
            'encerrada_em' => $now,
            'encerramento_motivo' => $reason,
        ])->save();
        AuditLog::create([
            'action' => $status === ReservationStatus::Expired ? 'reservation.expired' : 'reservation.cancelled',
            'subject_id' => $reservation->usuario_id,
            'metadata' => ['reserva_id' => $reservation->id, 'livro_id' => $reservation->livro_id, 'exemplar_id' => $reservation->exemplar_id, 'reason' => $reason],
        ]);
    }
}
