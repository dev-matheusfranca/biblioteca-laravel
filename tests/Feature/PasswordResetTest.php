<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_account_receives_reset_notification_only_after_request(): void
    {
        Notification::fake();
        $user = User::factory()->reader()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_inactive_account_receives_the_same_generic_response_without_reset_notification(): void
    {
        Notification::fake();
        $user = User::factory()->reader()->inactive()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status', 'Se houver uma conta ativa com este e-mail, você receberá as instruções para redefinir a senha.');

        Notification::assertNotSentTo($user, ResetPassword::class);
    }

    public function test_active_account_can_reset_its_password_with_a_valid_token(): void
    {
        $user = User::factory()->reader()->create();
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nova-senha-segura',
            'password_confirmation' => 'nova-senha-segura',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('nova-senha-segura', $user->fresh()->password));
    }

    public function test_inactive_account_cannot_reset_its_password_even_with_a_valid_token(): void
    {
        $user = User::factory()->reader()->inactive()->create();
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nova-senha-segura',
            'password_confirmation' => 'nova-senha-segura',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_reset_revokes_personal_tokens_and_all_database_sessions(): void
    {
        config([
            'session.driver' => 'database',
            'session.connection' => config('database.default'),
        ]);
        app('session')->forgetDrivers();
        $user = User::factory()->reader()->create();
        $user->createToken('Old token', ['personal:read'], now()->addHour());
        $guardKey = Auth::guard('web')->getName();
        $oldSessionIds = ['old-session-a', 'old-session-b'];
        foreach ($oldSessionIds as $sessionId) {
            DB::table('sessions')->insert([
                'id' => $sessionId,
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test client',
                'payload' => base64_encode(serialize([$guardKey => $user->id])),
                'last_activity' => now()->timestamp,
            ]);
        }
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nova-senha-segura',
            'password_confirmation' => 'nova-senha-segura',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        foreach ($oldSessionIds as $sessionId) {
            $this->app['auth']->forgetGuards();
            $this->withCookie((string) config('session.cookie'), $sessionId)
                ->get(route('portal.index'))
                ->assertRedirect(route('login'));
        }
    }

    public function test_reset_from_the_authenticated_session_does_not_recreate_its_login(): void
    {
        config([
            'session.driver' => 'database',
            'session.connection' => config('database.default'),
        ]);
        app('session')->forgetDrivers();
        $user = User::factory()->reader()->create();
        $token = Password::createToken($user);

        $this->actingAs($user)->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nova-senha-segura',
            'password_confirmation' => 'nova-senha-segura',
        ])->assertRedirect(route('login'));

        $this->get(route('portal.index'))->assertRedirect(route('login'));
    }
}
