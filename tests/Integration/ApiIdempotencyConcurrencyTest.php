<?php

namespace Tests\Integration;

use App\Models\ApiIdempotencyRecord;
use App\Models\CommunicationOutbox;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\PhysicalCatalog;
use Tests\TestCase;

class ApiIdempotencyConcurrencyTest extends TestCase
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

    public function test_simultaneous_requests_with_the_same_key_apply_the_mutation_once(): void
    {
        $reader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        $token = $reader->createToken('race', ['reservations:write'], now()->addHour())->accessToken;
        $key = (string) Str::uuid();

        $results = $this->race($reader->id, $token->id, $book->id, $key);

        $this->assertEqualsCanonicalizing(['created', 'replayed'], array_column($results, 'result'));
        $this->assertCount(1, array_unique(array_column($results, 'id')));
        $this->assertSame(1, Reserva::query()->where('usuario_id', $reader->id)->where('livro_id', $book->id)->count());
        $this->assertSame(1, ApiIdempotencyRecord::query()->where('usuario_id', $reader->id)->count());
        $this->assertSame(1, CommunicationOutbox::query()->where('type', 'reservation.available')->count());
    }

    /** @return list<array{result: string, id: int}> */
    private function race(int $readerId, int $tokenId, int $bookId, string $key): array
    {
        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        $barrier = $directory.'/api-race-'.bin2hex(random_bytes(12));
        $processes = [];

        try {
            foreach ([0, 1] as $participant) {
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Support/api-idempotency-race.php'),
                    (string) $readerId,
                    (string) $tokenId,
                    (string) $bookId,
                    $key,
                    $barrier,
                    (string) $participant,
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

            $deadline = microtime(true) + 15;
            do {
                $ready = count(glob($barrier.'.*.ready')) === count($processes);
                if (! $ready) {
                    usleep(10000);
                }
            } while (! $ready && microtime(true) < $deadline);
            $this->assertTrue($ready, 'Both independent API clients must reach the start barrier.');
            file_put_contents($barrier.'.go', 'go');

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
