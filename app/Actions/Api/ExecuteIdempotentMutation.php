<?php

namespace App\Actions\Api;

use App\Enums\UserRole;
use App\Exceptions\IdempotencyConflictException;
use App\Models\ApiIdempotencyRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class ExecuteIdempotentMutation
{
    /**
     * @param  Closure(User): array{status:int, body:array<string, mixed>}  $mutation
     */
    public function execute(Request $request, User $actor, Closure $mutation): IdempotentResult
    {
        $key = (string) $request->header('Idempotency-Key');
        $keyHash = hash('sha256', $key);
        $requestHash = $this->requestHash($request);

        return DB::transaction(function () use ($actor, $keyHash, $mutation, $request, $requestHash) {
            $reader = User::query()->lockForUpdate()->findOrFail($actor->id);
            if (! $reader->isActive() || $reader->role !== UserRole::Reader) {
                throw new DomainException('A conta não está disponível para esta operação.');
            }
            $presentedToken = $actor->currentAccessToken();
            $requiredAbility = $request->attributes->get('api_required_ability');
            if (! is_string($requiredAbility)) {
                throw new AuthorizationException('O token deixou de ser válido para esta operação.');
            }
            $token = PersonalAccessToken::query()->lockForUpdate()->find($presentedToken->id);
            $globalExpiration = config('sanctum.expiration');
            $globallyExpired = $token !== null
                && is_numeric($globalExpiration)
                && CarbonImmutable::parse($token->created_at)->lte(now()->subMinutes((int) $globalExpiration));
            if (! $token
                || $token->tokenable_id !== $reader->id
                || $token->tokenable_type !== $reader->getMorphClass()
                || $globallyExpired
                || ($token->expires_at !== null && CarbonImmutable::parse($token->expires_at)->isPast())
                || ! $token->can($requiredAbility)) {
                throw new AuthorizationException('O token deixou de ser válido para esta operação.');
            }

            $record = ApiIdempotencyRecord::query()
                ->where('usuario_id', $reader->id)
                ->where('key_hash', $keyHash)
                ->lockForUpdate()
                ->first();

            if ($record?->expires_at?->isPast()) {
                $record->delete();
                $record = null;
            }
            if ($record) {
                if (! hash_equals($record->request_hash, $requestHash)) {
                    throw new IdempotencyConflictException;
                }

                return new IdempotentResult($record->response_status, $record->response_body, true);
            }

            $result = $mutation($reader);
            ApiIdempotencyRecord::create([
                'usuario_id' => $reader->id,
                'key_hash' => $keyHash,
                'request_hash' => $requestHash,
                'response_status' => $result['status'],
                'response_body' => $result['body'],
                'expires_at' => now()->addHours(24),
            ]);

            return new IdempotentResult($result['status'], $result['body'], false);
        }, 3);
    }

    private function requestHash(Request $request): string
    {
        $payload = $this->normalize($request->all());

        return hash('sha256', implode("\n", [
            strtoupper($request->method()),
            '/'.$request->path(),
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]));
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
    }
}
