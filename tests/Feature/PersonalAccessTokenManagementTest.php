<?php

namespace Tests\Feature;

use App\Actions\Identity\UpdateTeamMember;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class PersonalAccessTokenManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_reader_creates_a_24_hour_subset_token_and_secret_is_shown_once(): void
    {
        $reader = User::factory()->reader()->create();

        $response = $this->actingAs($reader)->post(route('tokens.store'), [
            'name' => 'Demonstração',
            'current_password' => 'password',
            'abilities' => ['personal:read', 'loans:renew'],
        ]);

        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        preg_match('/value="([^"]+\|bib_[^"]+)"/', $response->getContent(), $matches);
        $secret = $matches[1] ?? null;
        $this->assertIsString($secret);
        $this->assertStringContainsString('|bib_', $secret);
        $token = PersonalAccessToken::query()->sole();
        $this->assertSame(['loans:renew', 'personal:read'], $token->abilities);
        $this->assertTrue($token->expires_at->between(now()->addHours(23)->addMinutes(59), now()->addHours(24)->addMinute()));
        $this->assertStringNotContainsString($secret, $token->token);

        $this->assertFalse(session()->has('new_token'));
        $this->assertStringNotContainsString($secret, (string) session()->getHandler()->read(session()->getId()));
        $this->get(route('tokens.index'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertDontSee($secret);
    }

    public function test_token_creation_requires_current_password_allowlist_and_limit(): void
    {
        $reader = User::factory()->reader()->create();

        $this->actingAs($reader)->post(route('tokens.store'), [
            'name' => 'Inválido',
            'current_password' => 'wrong-password',
            'abilities' => ['*'],
        ])->assertSessionHasErrors(['current_password', 'abilities.0']);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        foreach (range(1, 10) as $number) {
            $reader->createToken("Token {$number}", ['personal:read'], now()->addHour());
        }
        $this->actingAs($reader)->post(route('tokens.store'), [
            'name' => 'Décimo primeiro',
            'current_password' => 'password',
            'abilities' => ['personal:read'],
        ])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('personal_access_tokens', 10);
    }

    public function test_reader_can_revoke_only_an_owned_token_and_staff_cannot_manage_personal_tokens(): void
    {
        $reader = User::factory()->reader()->create();
        $other = User::factory()->reader()->create();
        $staff = User::factory()->librarian()->create();
        $ownToken = $reader->createToken('Own', ['personal:read'], now()->addHour())->accessToken;
        $otherToken = $other->createToken('Other', ['personal:read'], now()->addHour())->accessToken;

        $this->actingAs($reader)->delete(route('tokens.destroy', $otherToken->id))->assertNotFound();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->id]);
        $this->actingAs($reader)->delete(route('tokens.destroy', $ownToken->id))->assertRedirect();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $ownToken->id]);
        $this->actingAs($staff)->get(route('tokens.index'))->assertForbidden();
    }

    public function test_password_reset_inactivation_and_role_change_revoke_tokens(): void
    {
        $reader = User::factory()->reader()->create();
        $reader->createToken('Reset', ['personal:read'], now()->addHour());
        $reset = Password::createToken($reader);
        $this->post(route('password.update'), [
            'token' => $reset,
            'email' => $reader->email,
            'password' => 'nova-senha-segura',
            'password_confirmation' => 'nova-senha-segura',
        ])->assertRedirect(route('login'));
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $reader->createToken('Inactive', ['personal:read'], now()->addHour());
        $staff = User::factory()->librarian()->create();
        $this->actingAs($staff)->patch(route('leitores.update', $reader), [
            'name' => $reader->name,
            'email' => $reader->email,
            'is_active' => '0',
        ])->assertRedirect(route('leitores.index'));
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $reader->forceFill(['is_active' => true])->save();
        $reader->createToken('Role', ['personal:read'], now()->addHour());
        $admin = User::factory()->admin()->create();
        app(UpdateTeamMember::class)->execute($admin, $reader, UserRole::Librarian, true);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_administrative_password_or_role_change_revokes_database_sessions(): void
    {
        config([
            'session.driver' => 'database',
            'session.connection' => config('database.default'),
        ]);
        app('session')->forgetDrivers();
        $reader = User::factory()->reader()->create();
        $staff = User::factory()->librarian()->create();
        $passwordSession = $this->databaseSession($reader, 'reader-password-session');

        $this->actingAs($staff)->patch(route('leitores.update', $reader), [
            'name' => $reader->name,
            'email' => $reader->email,
            'password' => 'senha-alterada-segura',
            'password_confirmation' => 'senha-alterada-segura',
            'is_active' => '1',
        ])->assertRedirect(route('leitores.index'));
        $this->assertDatabaseMissing('sessions', ['id' => $passwordSession]);
        $this->app['auth']->forgetGuards();
        $this->withCookie((string) config('session.cookie'), $passwordSession)
            ->get(route('portal.index'))->assertRedirect(route('login'));

        $roleSession = $this->databaseSession($reader->fresh(), 'reader-role-session');
        $admin = User::factory()->admin()->create();
        app(UpdateTeamMember::class)->execute($admin, $reader->fresh(), UserRole::Librarian, true);
        $this->assertDatabaseMissing('sessions', ['id' => $roleSession]);
        $this->app['auth']->forgetGuards();
        $this->withCookie((string) config('session.cookie'), $roleSession)
            ->get(route('portal.index'))->assertRedirect(route('login'));
    }

    private function databaseSession(User $user, string $sessionId): string
    {
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test client',
            'payload' => base64_encode(serialize([Auth::guard('web')->getName() => $user->id])),
            'last_activity' => now()->timestamp,
        ]);

        return $sessionId;
    }
}
