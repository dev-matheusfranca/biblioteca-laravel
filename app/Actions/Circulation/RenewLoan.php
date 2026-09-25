<?php

namespace App\Actions\Circulation;

use App\Models\AuditLog;
use App\Models\Livro;
use App\Models\LoanRenewal;
use App\Models\Locacao;
use App\Models\User;
use App\Services\CirculationEligibility;
use App\Services\CurrentCirculationPolicy;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class RenewLoan
{
    public function __construct(
        private readonly CurrentCirculationPolicy $policies,
        private readonly CirculationEligibility $eligibility,
        private readonly AllocateReservationsForBook $allocator,
    ) {}

    public function execute(User $actor, Locacao $loan): LoanRenewal
    {
        $readerId = $loan->usuario_id;
        $bookId = $loan->livro_id;
        $loanId = $loan->id;

        return DB::transaction(function () use ($actor, $readerId, $bookId, $loanId) {
            $reader = User::query()->lockForUpdate()->findOrFail($readerId);
            $book = Livro::query()->lockForUpdate()->findOrFail($bookId);
            $currentActor = $actor->fresh();
            if (! $currentActor?->isActive() || ($currentActor->id !== $readerId && ! $currentActor->isStaff())) {
                throw new DomainException('Você não pode renovar este empréstimo.');
            }

            $lockedLoan = Locacao::query()->lockForUpdate()->findOrFail($loanId);
            $this->allocator->executeLocked($book);
            $policy = $this->policies->get();
            if ($reason = $this->eligibility->renewalBlockReason($reader, $lockedLoan, $policy)) {
                throw new DomainException($reason);
            }

            $previousDueDate = CarbonImmutable::parse($lockedLoan->data_devolucao, $policy->timezone)->startOfDay();
            $newDueDate = $previousDueDate->addDays($policy->renewal_days);
            $snapshot = $policy->snapshot();
            $lockedLoan->forceFill([
                'data_devolucao' => $newDueDate->toDateString(),
                'renewal_count' => $lockedLoan->renewal_count + 1,
            ])->save();
            $renewal = LoanRenewal::create([
                'locacao_id' => $lockedLoan->id,
                'actor_id' => $currentActor->id,
                'policy_id' => $policy->id,
                'previous_due_date' => $previousDueDate->toDateString(),
                'new_due_date' => $newDueDate->toDateString(),
                'policy_snapshot' => $snapshot,
            ]);
            AuditLog::create([
                'action' => 'loan.renewed',
                'actor_id' => $currentActor->id,
                'subject_id' => $reader->id,
                'metadata' => ['locacao_id' => $lockedLoan->id, 'livro_id' => $book->id, 'previous_due_date' => $previousDueDate->toDateString(), 'new_due_date' => $newDueDate->toDateString(), 'policy_version' => $policy->version],
            ]);

            return $renewal;
        }, 3);
    }

    public function blockReason(User $actor, Locacao $loan): ?string
    {
        $reader = $loan->relationLoaded('usuario') ? $loan->usuario : $loan->usuario()->first();
        if (! $reader || ! $actor->isActive() || ($actor->id !== $reader->id && ! $actor->isStaff())) {
            return 'Você não pode renovar este empréstimo.';
        }

        return $this->eligibility->renewalBlockReason($reader, $loan, $this->policies->get());
    }
}
