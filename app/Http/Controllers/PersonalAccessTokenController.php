<?php

namespace App\Http\Controllers;

use App\Actions\Api\IssuePersonalAccessToken;
use App\Enums\UserRole;
use App\Http\Requests\Token\StorePersonalAccessTokenRequest;
use App\Models\User;
use App\Support\PersonalTokenAbilities;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class PersonalAccessTokenController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureReader($request->user());
        $tokens = $request->user()->tokens()
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('created_at')
            ->get();
        $abilities = PersonalTokenAbilities::ALL;

        return $this->render($tokens, $abilities);
    }

    public function store(StorePersonalAccessTokenRequest $request, IssuePersonalAccessToken $issueToken)
    {
        $this->ensureReader($request->user());
        $data = $request->validated();
        $newToken = $issueToken->execute(
            $request->user(),
            $data['current_password'],
            $data['name'],
            $data['abilities'],
        );

        $tokens = $request->user()->tokens()
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('created_at')
            ->get();

        return $this->render($tokens, PersonalTokenAbilities::ALL, $newToken);
    }

    public function destroy(Request $request, int $token)
    {
        $this->ensureReader($request->user());
        $personalToken = $request->user()->tokens()->whereKey($token)->firstOrFail();
        $personalToken->delete();

        return back()->with('success', 'Token revogado.');
    }

    private function ensureReader(User $user): void
    {
        abort_unless($user->isActive() && $user->role === UserRole::Reader, 403);
    }

    /**
     * @param  Collection<int, PersonalAccessToken>  $tokens
     * @param  array<string, string>  $abilities
     */
    private function render(Collection $tokens, array $abilities, ?string $newToken = null): Response
    {
        return response()
            ->view('portal.tokens.index', compact('tokens', 'abilities', 'newToken'))
            ->header('Cache-Control', 'no-store, private');
    }
}
