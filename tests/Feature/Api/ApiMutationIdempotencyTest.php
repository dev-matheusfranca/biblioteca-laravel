<?php

namespace Tests\Feature\Api;

use App\Actions\Api\ExecuteIdempotentMutation;
use App\Actions\Circulation\CreateReservation;
use App\Models\ApiIdempotencyRecord;
use App\Models\AuditLog;
use App\Models\LoanRenewal;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\Support\PhysicalCatalog;
use Tests\TestCase;

class ApiMutationIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_replay_returns_the_persisted_response_without_duplicate_side_effects(): void
    {
        $reader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        $token = $reader->createToken('reserve', ['reservations:write'], now()->addHour())->plainTextToken;
        $key = (string) Str::uuid();

        $first = $this->withToken($token)->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/livros/{$book->id}/reservas")
            ->assertCreated()
            ->assertHeaderMissing('Idempotency-Replayed');
        $second = $this->withToken($token)->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/livros/{$book->id}/reservas")
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');

        $second->assertExactJson($first->json());
        $this->assertDatabaseCount('reservas', 1);
        $this->assertDatabaseCount('communication_outbox', 1);
        $this->assertDatabaseCount('api_idempotency_records', 1);
        $record = ApiIdempotencyRecord::query()->sole();
        $this->assertSame(hash('sha256', $key), $record->key_hash);
        $this->assertStringNotContainsString($key, json_encode($record->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_same_key_with_another_request_returns_conflict_before_mutating(): void
    {
        $reader = User::factory()->reader()->create();
        [, , $firstBook] = PhysicalCatalog::book();
        [, , $secondBook] = PhysicalCatalog::book();
        $token = $reader->createToken('reserve', ['reservations:write'], now()->addHour())->plainTextToken;
        $key = (string) Str::uuid();

        $this->withToken($token)->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/livros/{$firstBook->id}/reservas")->assertCreated();
        $this->withToken($token)->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/livros/{$secondBook->id}/reservas")
            ->assertConflict()
            ->assertJsonStructure(['message', 'request_id']);

        $this->assertDatabaseCount('reservas', 1);
    }

    public function test_mutations_require_the_exact_ability_before_idempotency_processing(): void
    {
        $reader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        $readToken = $reader->createToken('read', ['personal:read'], now()->addHour())->plainTextToken;

        $this->withToken($readToken)->postJson("/api/v1/livros/{$book->id}/reservas")
            ->assertForbidden();
        $this->assertDatabaseCount('reservas', 0);
        $this->assertDatabaseCount('api_idempotency_records', 0);

        $writeToken = $reader->createToken('write', ['reservations:write'], now()->addHour())->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($writeToken)->postJson("/api/v1/livros/{$book->id}/reservas")
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['Idempotency-Key']]);
    }

    public function test_renewal_replay_records_only_one_renewal(): void
    {
        $reader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        $loan = PhysicalCatalog::loan($reader, $book);
        $token = $reader->createToken('renew', ['loans:renew'], now()->addHour())->plainTextToken;
        $key = (string) Str::uuid();

        $first = $this->withToken($token)->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/emprestimos/{$loan->id}/renovacoes")
            ->assertOk();
        $second = $this->withToken($token)->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/emprestimos/{$loan->id}/renovacoes")
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true');

        $second->assertExactJson($first->json());
        $this->assertSame(1, $loan->fresh()->renewal_count);
        $this->assertSame(1, LoanRenewal::query()->where('locacao_id', $loan->id)->count());
    }

    public function test_cancellation_replay_and_expired_key_reuse_are_safe(): void
    {
        $reader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        $reservation = app(CreateReservation::class)->execute($reader, $book->id);
        $token = $reader->createToken('reserve', ['reservations:write'], now()->addHour())->plainTextToken;
        $key = (string) Str::uuid();

        $this->withToken($token)->withHeader('Idempotency-Key', $key)
            ->deleteJson("/api/v1/reservas/{$reservation->id}")->assertOk();
        $this->withToken($token)->withHeader('Idempotency-Key', $key)
            ->deleteJson("/api/v1/reservas/{$reservation->id}")
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame(1, Reserva::query()->whereKey($reservation->id)->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'reservation.cancelled')->count());

        ApiIdempotencyRecord::query()->update(['expires_at' => now()->subMinute()]);
        [, , $otherBook] = PhysicalCatalog::book();
        $this->withToken($token)->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/livros/{$otherBook->id}/reservas")->assertCreated();
        $this->assertDatabaseCount('api_idempotency_records', 1);
    }

    public function test_failed_domain_mutation_does_not_persist_an_idempotency_response(): void
    {
        $reader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        app(CreateReservation::class)->execute($reader, $book->id);
        $token = $reader->createToken('reserve', ['reservations:write'], now()->addHour())->plainTextToken;

        $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/livros/{$book->id}/reservas")->assertUnprocessable();

        $this->assertDatabaseCount('api_idempotency_records', 0);
    }

    public function test_current_account_authorization_is_checked_before_a_replay(): void
    {
        $reader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        $token = $reader->createToken('reserve', ['reservations:write'], now()->addHour())->plainTextToken;
        $key = (string) Str::uuid();
        $this->withToken($token)->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/livros/{$book->id}/reservas")->assertCreated();

        $reader->forceFill(['is_active' => false])->save();
        $this->withToken($token)->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/livros/{$book->id}/reservas")->assertForbidden();
        $this->assertDatabaseCount('reservas', 1);
    }

    public function test_mutation_revalidates_a_token_revoked_after_the_initial_middleware_check(): void
    {
        $reader = User::factory()->reader()->create();
        $newToken = $reader->createToken('write', ['reservations:write'], now()->addHour());
        $reader->withAccessToken($newToken->accessToken);
        $request = Request::create('/api/v1/livros/1/reservas', 'POST', server: [
            'HTTP_IDEMPOTENCY_KEY' => (string) Str::uuid(),
        ]);
        $request->attributes->set('api_required_ability', 'reservations:write');
        $newToken->accessToken->delete();
        $called = false;

        try {
            app(ExecuteIdempotentMutation::class)->execute($request, $reader, function () use (&$called) {
                $called = true;

                return ['status' => 200, 'body' => ['data' => []]];
            });
            $this->fail('A mutação deveria rejeitar o token revogado.');
        } catch (AuthorizationException) {
        }

        $this->assertFalse($called);
        $this->assertDatabaseCount('api_idempotency_records', 0);
    }
}
