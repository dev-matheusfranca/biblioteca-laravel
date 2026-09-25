<?php

namespace App\Actions\Circulation;

use App\Actions\Inventory\SynchronizeLivroAvailability;
use App\Enums\ExemplarCondition;
use App\Models\AuditLog;
use App\Models\Exemplar;
use App\Models\Livro;
use App\Models\Locacao;
use App\Models\Reserva;
use App\Models\User;
use App\Services\CirculationEligibility;
use App\Services\CurrentCirculationPolicy;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CheckoutExemplar
{
    public function __construct(
        private readonly CurrentCirculationPolicy $policies,
        private readonly CirculationEligibility $eligibility,
        private readonly AllocateReservationsForBook $allocator,
    ) {}

    public function execute(User $actor, int $readerId, int $bookId, string $dueDate = '', ?int $exemplarId = null): Locacao
    {
        try {
            return DB::transaction(function () use ($actor, $readerId, $bookId, $exemplarId) {
                $reader = User::query()->lockForUpdate()->findOrFail($readerId);
                $book = Livro::query()->lockForUpdate()->findOrFail($bookId);

                if (! $actor->fresh()?->isStaff()) {
                    throw new DomainException('Operador sem permissão para registrar empréstimos.');
                }
                $policy = $this->policies->get();
                if ($reason = $this->eligibility->checkoutBlockReason($reader, $book, $policy)) {
                    throw new DomainException($reason);
                }

                $this->allocator->executeLocked($book);
                $reservation = Reserva::query()
                    ->where('active_key', Reserva::activeKey($reader->id, $book->id))
                    ->lockForUpdate()
                    ->first();

                if ($reservation?->active_exemplar_id) {
                    if ($exemplarId && $reservation->active_exemplar_id !== $exemplarId) {
                        throw new DomainException('A retirada deve usar o exemplar separado para esta reserva.');
                    }
                    $exemplar = Exemplar::query()->whereKey($reservation->active_exemplar_id)->lockForUpdate()->first();
                } else {
                    $exemplar = Exemplar::query()
                        ->where('livro_id', $book->id)
                        ->where('condicao', ExemplarCondition::Circulation->value)
                        ->where('identificacao_fisica', true)
                        ->when($exemplarId, fn ($query) => $query->whereKey($exemplarId))
                        ->whereDoesntHave('locacaoAtiva')
                        ->whereDoesntHave('reservaAtiva')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->first();
                }

                if (! $exemplar) {
                    throw new DomainException($reservation
                        ? 'Sua reserva aguarda a liberação de um exemplar.'
                        : 'Não há exemplar em circulação disponível para este empréstimo.');
                }

                $today = CarbonImmutable::now($policy->timezone)->startOfDay();
                $locacao = Locacao::create([
                    'usuario_id' => $reader->id,
                    'livro_id' => $book->id,
                    'exemplar_id' => $exemplar->id,
                    'active_exemplar_id' => $exemplar->id,
                    'policy_id' => $policy->id,
                    'policy_snapshot' => $policy->snapshot(),
                    'renewal_count' => 0,
                    'data_locacao' => $today->toDateString(),
                    'data_devolucao' => $today->addDays($policy->loan_days)->toDateString(),
                    'status' => 'ativa',
                ]);

                if ($reservation) {
                    $reservation->forceFill([
                        'status' => 'atendida',
                        'active_key' => null,
                        'active_exemplar_id' => null,
                        'exemplar_id' => $exemplar->id,
                        'encerrada_em' => CarbonImmutable::now($policy->timezone),
                        'encerramento_motivo' => 'retirada_realizada',
                    ])->save();
                    AuditLog::create(['action' => 'reservation.fulfilled', 'actor_id' => $actor->id, 'subject_id' => $reader->id, 'metadata' => ['reserva_id' => $reservation->id, 'locacao_id' => $locacao->id, 'livro_id' => $book->id]]);
                }

                app(SynchronizeLivroAvailability::class)->execute($book);
                AuditLog::create(['action' => 'loan.checked_out', 'actor_id' => $actor->id, 'subject_id' => $reader->id, 'metadata' => ['livro_id' => $book->id, 'exemplar_id' => $exemplar->id, 'locacao_id' => $locacao->id, 'policy_version' => $policy->version]]);

                return $locacao;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('O exemplar acabou de ser destinado a outra operação. Atualize e tente novamente.');
        }
    }
}
