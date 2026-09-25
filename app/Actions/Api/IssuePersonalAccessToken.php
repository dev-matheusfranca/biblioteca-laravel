<?php

namespace App\Actions\Api;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\PersonalTokenAbilities;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class IssuePersonalAccessToken
{
    /** @param list<string> $abilities */
    public function execute(User $actor, string $currentPassword, string $name, array $abilities): string
    {
        return DB::transaction(function () use ($abilities, $actor, $currentPassword, $name): string {
            $reader = User::query()->lockForUpdate()->findOrFail($actor->id);
            if (! $reader->isActive() || $reader->role !== UserRole::Reader) {
                throw new AuthorizationException('A conta não pode emitir tokens pessoais.');
            }
            if (! Hash::check($currentPassword, $reader->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'A senha atual não confere.',
                ]);
            }
            $normalizedAbilities = collect($abilities)->unique()->sort()->values()->all();
            if ($normalizedAbilities === [] || array_diff($normalizedAbilities, array_keys(PersonalTokenAbilities::ALL)) !== []) {
                throw ValidationException::withMessages([
                    'abilities' => 'Selecione apenas permissões disponíveis.',
                ]);
            }
            $activeTokens = $reader->tokens()
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->count();
            if ($activeTokens >= 10) {
                throw ValidationException::withMessages([
                    'name' => 'Revogue um token antes de criar outro. O limite é de 10 tokens ativos.',
                ]);
            }

            return $reader->createToken(trim($name), $normalizedAbilities, now()->addHours(24))->plainTextToken;
        }, 3);
    }
}
