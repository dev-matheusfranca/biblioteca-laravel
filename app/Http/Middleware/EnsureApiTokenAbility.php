<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiTokenAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        if (! $user || ! $token instanceof PersonalAccessToken) {
            throw new AuthenticationException('Token pessoal obrigatório.');
        }
        $freshUser = User::query()->find($user->id);
        if (! $freshUser?->isActive() || $freshUser->role !== UserRole::Reader) {
            throw new AuthorizationException('Esta API pessoal está disponível somente para leitores ativos.');
        }
        if (! $token->can($ability)) {
            throw new AuthorizationException('O token não possui a permissão necessária.');
        }
        $request->attributes->set('api_required_ability', $ability);

        return $next($request);
    }
}
