<?php

namespace App\Actions\Circulation;

use App\Models\AuditLog;
use App\Models\Livro;
use App\Models\Reserva;
use App\Models\User;
use App\Services\CirculationEligibility;
use App\Services\CurrentCirculationPolicy;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CreateReservation
{
    public function __construct(
        private readonly CurrentCirculationPolicy $policies,
        private readonly CirculationEligibility $eligibility,
        private readonly AllocateReservationsForBook $allocator,
    ) {}

    public function execute(User $actor, int $bookId): Reserva
    {
        try {
            return DB::transaction(function () use ($actor, $bookId) {
                $reader = User::query()->lockForUpdate()->findOrFail($actor->id);
                $book = Livro::query()->lockForUpdate()->findOrFail($bookId);
                if (! $actor->fresh()?->isActive() || $reader->id !== $actor->id) {
                    throw new DomainException('Conta indisponível para realizar reservas.');
                }
                $policy = $this->policies->get();
                if ($reason = $this->eligibility->reservationBlockReason($reader, $book, $policy)) {
                    throw new DomainException($reason);
                }

                $reservation = Reserva::create([
                    'usuario_id' => $reader->id,
                    'livro_id' => $book->id,
                    'status' => 'aguardando',
                    'active_key' => Reserva::activeKey($reader->id, $book->id),
                    'policy_id' => $policy->id,
                    'policy_snapshot' => $policy->snapshot(),
                ]);
                AuditLog::create([
                    'action' => 'reservation.created',
                    'actor_id' => $actor->id,
                    'subject_id' => $reader->id,
                    'metadata' => ['reserva_id' => $reservation->id, 'livro_id' => $book->id, 'policy_version' => $policy->version],
                ]);
                $this->allocator->executeLocked($book);

                return $reservation->refresh();
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('Você já possui uma reserva ativa para este título.');
        }
    }
}
