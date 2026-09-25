<?php

namespace App\Actions\Circulation;

use App\Models\AuditLog;
use App\Models\CirculationPolicy;
use App\Models\User;
use App\Services\CurrentCirculationPolicy;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class UpdateCirculationPolicy
{
    public function __construct(private readonly CurrentCirculationPolicy $policies) {}

    /** @param array{loan_days:int,max_open_loans:int,max_renewals:int,renewal_days:int,pickup_hours:int,blocks_overdue:bool,timezone:string,reason:string,expected_version:int} $data */
    public function execute(User $actor, array $data): CirculationPolicy
    {
        try {
            return DB::transaction(function () use ($actor, $data) {
                $current = $this->policies->get(lockForUpdate: true);
                if (! $actor->fresh()?->isAdmin()) {
                    throw new DomainException('Somente administradores ativos podem alterar a política de circulação.');
                }
                if ($current->version !== $data['expected_version']) {
                    throw new DomainException('A política foi alterada por outra pessoa. Recarregue a página antes de salvar.');
                }

                $current->forceFill(['active_key' => null])->save();
                $next = CirculationPolicy::create([
                    'version' => $current->version + 1,
                    'loan_days' => $data['loan_days'],
                    'max_open_loans' => $data['max_open_loans'],
                    'max_renewals' => $data['max_renewals'],
                    'renewal_days' => $data['renewal_days'],
                    'pickup_hours' => $data['pickup_hours'],
                    'blocks_overdue' => $data['blocks_overdue'],
                    'timezone' => $data['timezone'],
                    'active_key' => 'current',
                    'changed_by' => $actor->id,
                    'change_reason' => $data['reason'],
                ]);
                AuditLog::create([
                    'action' => 'circulation_policy.updated',
                    'actor_id' => $actor->id,
                    'metadata' => ['previous_version' => $current->version, 'version' => $next->version, 'reason' => $data['reason']],
                ]);

                return $next;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('A política foi alterada por outra pessoa. Recarregue a página antes de salvar.');
        }
    }
}
