<?php

namespace App\Actions\Identity;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class UpdateTeamMember
{
    public function __construct(private readonly RevokeUserAccess $revokeAccess) {}

    public function execute(?User $actor, User $subject, UserRole $role, bool $isActive, string $action = 'team_member.updated'): User
    {
        return DB::transaction(function () use ($actor, $subject, $role, $isActive, $action) {
            $lockedUsers = User::query()
                ->whereKey(collect([$actor?->getKey(), $subject->getKey()])->filter()->unique()->sort()->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var User $lockedSubject */
            $lockedSubject = $lockedUsers->get($subject->getKey()) ?? abort(404);
            /** @var User|null $lockedActor */
            $lockedActor = $actor ? $lockedUsers->get($actor->getKey()) : null;

            if ($lockedActor && ! $lockedActor->isAdmin()) {
                throw new DomainException('Sua conta não possui mais permissão para alterar a equipe.');
            }

            $roleChanged = $lockedSubject->role !== $role;
            $activeChanged = $lockedSubject->is_active !== $isActive;

            if (! $roleChanged && ! $activeChanged) {
                return $lockedSubject;
            }

            if ($lockedActor?->is($lockedSubject)) {
                throw new DomainException('Você não pode alterar o próprio papel ou acesso.');
            }

            if ($lockedSubject->role === UserRole::Admin && $lockedSubject->isActive() && ($role !== UserRole::Admin || ! $isActive)) {
                $activeAdmins = User::query()
                    ->where('role', UserRole::Admin->value)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($activeAdmins->count() <= 1) {
                    throw new DomainException('Mantenha ao menos um administrador ativo.');
                }
            }

            $before = [
                'role' => $lockedSubject->role->value,
                'is_active' => $lockedSubject->isActive(),
            ];

            $lockedSubject->forceFill([
                'role' => $role,
                'is_active' => $isActive,
            ])->save();
            if ($roleChanged || ! $isActive) {
                $this->revokeAccess->execute($lockedSubject);
            }

            AuditLog::create([
                'action' => $action,
                'actor_id' => $actor?->getKey(),
                'subject_id' => $lockedSubject->getKey(),
                'metadata' => [
                    'before' => $before,
                    'after' => [
                        'role' => $lockedSubject->role->value,
                        'is_active' => $lockedSubject->isActive(),
                    ],
                ],
            ]);

            return $lockedSubject;
        }, 3);
    }
}
