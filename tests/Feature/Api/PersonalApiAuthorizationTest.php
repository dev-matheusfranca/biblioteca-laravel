<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PhysicalCatalog;
use Tests\TestCase;

class PersonalApiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_endpoints_require_a_bearer_token_and_exact_ability(): void
    {
        $reader = User::factory()->reader()->create();

        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertHeader('X-Request-Id')
            ->assertJsonStructure(['message', 'request_id']);
        $this->actingAs($reader)->getJson('/api/v1/me')->assertUnauthorized();

        $wrongToken = $reader->createToken('wrong', ['reservations:write'], now()->addHour())->plainTextToken;
        $this->withToken($wrongToken)->getJson('/api/v1/me')->assertForbidden();

        $readToken = $reader->createToken('read', ['personal:read'], now()->addHour())->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($readToken)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $reader->id)
            ->assertJsonPath('data.papel', 'leitor');

        $expiredToken = $reader->createToken('expired', ['personal:read'], now()->subMinute())->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($expiredToken)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_only_active_readers_can_use_personal_tokens(): void
    {
        $staff = User::factory()->librarian()->create();
        $staffToken = $staff->createToken('staff', ['personal:read'], now()->addHour())->plainTextToken;
        $this->withToken($staffToken)->getJson('/api/v1/me')->assertForbidden();

        $reader = User::factory()->reader()->create();
        $readerToken = $reader->createToken('reader', ['personal:read'], now()->addHour())->plainTextToken;
        $reader->forceFill(['is_active' => false])->save();
        $this->app['auth']->forgetGuards();
        $this->withToken($readerToken)->getJson('/api/v1/me')->assertForbidden();
    }

    public function test_reader_sees_only_owned_loans_reservations_and_details(): void
    {
        $reader = User::factory()->reader()->create();
        $other = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        [, , $otherBook] = PhysicalCatalog::book();
        $loan = PhysicalCatalog::loan($reader, $book);
        $otherLoan = PhysicalCatalog::loan($other, $otherBook);
        $token = $reader->createToken('read', ['personal:read'], now()->addHour())->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me/emprestimos')
            ->assertOk()
            ->assertJsonPath('data.0.id', $loan->id)
            ->assertJsonMissing(['id' => $otherLoan->id]);
        $this->withToken($token)->getJson('/api/v1/me/emprestimos/'.$otherLoan->id)->assertNotFound();
        $this->withToken($token)->getJson('/api/v1/me/reservas')->assertOk()->assertJsonCount(0, 'data');
    }
}
