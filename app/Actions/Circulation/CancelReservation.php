<?php

namespace App\Actions\Circulation;

use App\Actions\Communication\CancelReservationAvailabilityCommunication;
use App\Enums\ReservationStatus;
use App\Models\AuditLog;
use App\Models\Livro;
use App\Models\Reserva;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class CancelReservation
{
    public function __construct(
        private readonly AllocateReservationsForBook $allocator,
        private readonly CancelReservationAvailabilityCommunication $cancelCommunication,
    ) {}

    public function execute(User $actor, Reserva $reservation, ?string $reason = null): bool
    {
        $readerId = $reservation->usuario_id;
        $bookId = $reservation->livro_id;
        $reservationId = $reservation->id;

        return DB::transaction(function () use ($actor, $readerId, $bookId, $reservationId, $reason) {
            User::query()->lockForUpdate()->findOrFail($readerId);
            $book = Livro::query()->lockForUpdate()->findOrFail($bookId);
            $currentActor = $actor->fresh();
            if (! $currentActor?->isActive() || ($currentActor->id !== $readerId && ! $currentActor->isStaff())) {
                throw new DomainException('Você não pode cancelar esta reserva.');
            }

            $locked = Reserva::query()->lockForUpdate()->findOrFail($reservationId);
            if (! $locked->status->isActive()) {
                return false;
            }
            $closedReason = $currentActor->id === $readerId ? 'cancelada_pelo_leitor' : 'cancelada_pela_equipe';
            $this->cancelCommunication->execute($locked);
            $locked->forceFill([
                'status' => ReservationStatus::Cancelled,
                'active_key' => null,
                'active_exemplar_id' => null,
                'encerrada_em' => now(),
                'encerramento_motivo' => $reason ? $closedReason.': '.trim($reason) : $closedReason,
            ])->save();
            AuditLog::create([
                'action' => 'reservation.cancelled',
                'actor_id' => $currentActor->id,
                'subject_id' => $readerId,
                'metadata' => ['reserva_id' => $locked->id, 'livro_id' => $book->id, 'reason' => $locked->encerramento_motivo],
            ]);
            $this->allocator->executeLocked($book);

            return true;
        }, 3);
    }
}
