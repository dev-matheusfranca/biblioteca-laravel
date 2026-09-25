<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_authenticated_user_with_form_name_mapping(): void
    {
        $response = $this->post(route('register.post'), [
            'nome' => 'Leitora Exemplo',
            'email' => 'leitora@example.test',
            'password' => 'segredo123',
            'password_confirmation' => 'segredo123',
        ]);

        $response->assertRedirect(route('portal.index'));
        $response->assertSessionMissing('_old_input.password');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Leitora Exemplo',
            'email' => 'leitora@example.test',
        ]);
    }

    public function test_invalid_login_keeps_only_email_as_old_input(): void
    {
        $response = $this->from(route('login'))->post(route('login.post'), [
            'email' => 'leitora@example.test',
            'password' => 'senha-incorreta',
        ]);

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors('email')
            ->assertSessionHasInput('email', 'leitora@example.test')
            ->assertSessionMissing('_old_input.password');
        $this->assertGuest();
    }

    public function test_valid_login_remembers_user_redirects_to_intended_and_regenerates_session(): void
    {
        $user = User::factory()->librarian()->create();
        $sessionIdBeforeLogin = $this->app->make('session')->getId();

        $response = $this->withSession(['url.intended' => route('livros.index')])
            ->post(route('login.post'), [
                'email' => $user->email,
                'password' => 'password',
                'remember' => '1',
            ]);

        $response->assertRedirect(route('livros.index'))
            ->assertCookie(Auth::guard()->getRecallerName());
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionIdBeforeLogin, $this->app->make('session')->getId());
    }

    public function test_logout_invalidates_the_authenticated_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['auth_marker' => 'must-be-cleared'])
            ->post(route('logout'));

        $response->assertRedirect(route('login'))
            ->assertSessionMissing('auth_marker');
        $this->assertGuest();
    }
}
