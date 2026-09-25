<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HorizonAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_for_dashboard_and_metrics_api(): void
    {
        foreach ($this->horizonUrls() as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_reader_cannot_access_dashboard_or_metrics_api(): void
    {
        $this->assertHorizonAccessForbidden(User::factory()->reader()->create());
    }

    public function test_librarian_cannot_access_dashboard_or_metrics_api(): void
    {
        $this->assertHorizonAccessForbidden(User::factory()->librarian()->create());
    }

    public function test_inactive_admin_is_logged_out_and_denied_dashboard_and_metrics_api(): void
    {
        $admin = User::factory()->admin()->inactive()->create();

        foreach ($this->horizonUrls() as $url) {
            $this->actingAs($admin)->get($url)->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_active_admin_can_render_the_dashboard_without_accessing_redis_data(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('horizon.index'))
            ->assertOk();
    }

    public function test_local_environment_does_not_bypass_authorization_for_reader(): void
    {
        $reader = User::factory()->reader()->create();
        $originalEnvironment = $this->app->environment();

        $this->app->instance('env', 'local');

        try {
            $this->assertHorizonAccessForbidden($reader);
        } finally {
            $this->app->instance('env', $originalEnvironment);
        }
    }

    private function assertHorizonAccessForbidden(User $user): void
    {
        foreach ($this->horizonUrls() as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
    }

    /** @return list<string> */
    private function horizonUrls(): array
    {
        return [
            route('horizon.index'),
            route('horizon.masters.index'),
        ];
    }
}
