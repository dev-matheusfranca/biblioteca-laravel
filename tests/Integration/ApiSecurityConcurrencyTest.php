<?php

namespace Tests\Integration;

use App\Models\ApiIdempotencyRecord;
use App\Models\CommunicationOutbox;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;
use Tests\Support\PhysicalCatalog;
use Tests\TestCase;

class ApiSecurityConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        // Committed fixtures must be visible to the two independent connections.
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_mutation_authenticated_before_revocation_revalidates_before_writing(): void
    {
        $reader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        $token = $reader->createToken('race', ['reservations:write'], now()->addHour())->accessToken;

        $results = $this->interleave([
            ['mutation-after-revoke', $reader->id, $token->id, (string) $book->id],
            ['revoke-after-auth', $reader->id, $token->id, 'unused'],
        ]);

        $this->assertEqualsCanonicalizing(['rejected', 'revoked'], array_column($results, 'result'));
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
        $this->assertSame(0, Reserva::query()->count());
        $this->assertSame(0, ApiIdempotencyRecord::query()->count());
        $this->assertSame(0, CommunicationOutbox::query()->count());
    }

    public function test_token_request_prevalidated_with_old_password_is_rejected_after_reset(): void
    {
        $reader = User::factory()->reader()->create();

        $results = $this->interleave([
            ['issue-after-reset', $reader->id, 0, 'unused'],
            ['reset-after-validation', $reader->id, 0, 'unused'],
        ]);

        $this->assertEqualsCanonicalizing(['rejected', 'reset'], array_column($results, 'result'));
        $this->assertTrue(Hash::check('nova-senha-segura', $reader->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * @param  list<array{0: string, 1: int, 2: int, 3: string}>  $participants
     * @return list<array{result: string}>
     */
    private function interleave(array $participants): array
    {
        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        $barrier = $directory.'/api-security-'.bin2hex(random_bytes(12));
        $processes = [];

        try {
            foreach ($participants as [$operation, $readerId, $subjectId, $key]) {
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Support/api-security-interleaving.php'),
                    $operation,
                    (string) $readerId,
                    (string) $subjectId,
                    $key,
                    $barrier,
                ], base_path(), [
                    'BIBLIOTECA_TEST_DRIVER' => 'mysql',
                    'APP_ENV' => 'testing',
                    'CACHE_STORE' => 'array',
                    'SESSION_DRIVER' => 'array',
                    'QUEUE_CONNECTION' => 'sync',
                    'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
                ], null, 25);
                $process->start();
                $processes[] = $process;
            }

            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), 'Concurrent child failed: '.$process->getOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach (glob($barrier.'.*') as $file) {
                unlink($file);
            }
        }
    }
}
