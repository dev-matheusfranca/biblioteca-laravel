<?php

namespace Tests\Integration;

use App\Actions\Circulation\AllocateReservationsForBook;
use App\Actions\Circulation\CloseLoan;
use App\Models\LoanRenewal;
use App\Models\Locacao;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Symfony\Component\Process\Process;
use Tests\Support\PhysicalCatalog;
use Tests\TestCase;

class CirculationConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        // Committed fixtures must be visible to child connections. The TestCase
        // guards this disposable database; production rollback guards stay active.
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_two_independent_processes_compete_for_the_last_copy(): void
    {
        $operator = User::factory()->librarian()->create();
        $readers = User::factory()->count(2)->create();
        [, , $book] = PhysicalCatalog::book();
        $results = $this->race([
            ['operation' => 'checkout', 'actor_id' => $operator->id, 'reader_id' => $readers[0]->id, 'subject_id' => $book->id],
            ['operation' => 'checkout', 'actor_id' => $operator->id, 'reader_id' => $readers[1]->id, 'subject_id' => $book->id],
        ]);
        $this->assertEqualsCanonicalizing(['created', 'rejected'], $results);
        $this->assertSame(1, Locacao::whereNull('encerrado_em')->count());
        $this->assertSame(0, $book->fresh()->quantidade_disponivel);
        $this->assertSame(1, Locacao::whereNotNull('active_exemplar_id')->count());
    }

    public function test_repeated_returns_in_independent_processes_release_only_once(): void
    {
        $operator = User::factory()->librarian()->create();
        $reader = User::factory()->create();
        [, , $book] = PhysicalCatalog::book();
        $loan = PhysicalCatalog::loan($reader, $book);
        $results = $this->race([
            ['operation' => 'return', 'actor_id' => $operator->id, 'reader_id' => $reader->id, 'subject_id' => $loan->id],
            ['operation' => 'return', 'actor_id' => $operator->id, 'reader_id' => $reader->id, 'subject_id' => $loan->id],
        ]);
        $this->assertEqualsCanonicalizing(['changed', 'unchanged'], $results);
        $this->assertSame(1, $book->fresh()->quantidade_disponivel);
        $this->assertSame(0, Locacao::whereNotNull('active_exemplar_id')->count());
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_two_checkouts_for_different_titles_leave_a_reader_with_at_most_three_open_loans(): void
    {
        $operator = User::factory()->librarian()->create();
        $reader = User::factory()->reader()->create();
        [, , $firstTitle] = PhysicalCatalog::book(['titulo' => 'Título aberto um']);
        [, , $secondTitle] = PhysicalCatalog::book(['titulo' => 'Título aberto dois']);
        PhysicalCatalog::loan($reader, $firstTitle);
        PhysicalCatalog::loan($reader, $secondTitle);
        [, , $thirdTitle] = PhysicalCatalog::book(['titulo' => 'Título concorrente três']);
        [, , $fourthTitle] = PhysicalCatalog::book(['titulo' => 'Título concorrente quatro']);

        $results = $this->race([
            ['operation' => 'checkout', 'actor_id' => $operator->id, 'reader_id' => $reader->id, 'subject_id' => $thirdTitle->id],
            ['operation' => 'checkout', 'actor_id' => $operator->id, 'reader_id' => $reader->id, 'subject_id' => $fourthTitle->id],
        ]);

        $this->assertEqualsCanonicalizing(['created', 'rejected'], $results);
        $this->assertSame(3, Locacao::query()->where('usuario_id', $reader->id)->whereNull('encerrado_em')->count());
    }

    public function test_two_renewals_of_the_same_loan_create_only_one_renewal(): void
    {
        $reader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        $loan = PhysicalCatalog::loan($reader, $book, ['data_devolucao' => now()->addWeek()->toDateString()]);

        $results = $this->race([
            ['operation' => 'renew', 'actor_id' => $reader->id, 'reader_id' => $reader->id, 'subject_id' => $loan->id],
            ['operation' => 'renew', 'actor_id' => $reader->id, 'reader_id' => $reader->id, 'subject_id' => $loan->id],
        ]);

        $this->assertEqualsCanonicalizing(['created', 'rejected'], $results);
        $this->assertSame(1, LoanRenewal::query()->where('locacao_id', $loan->id)->count());
        $this->assertSame(1, $loan->fresh()->renewal_count);
    }

    public function test_checkout_and_reservation_competing_for_the_last_copy_leave_only_one_physical_claim(): void
    {
        $operator = User::factory()->librarian()->create();
        $checkoutReader = User::factory()->reader()->create();
        $reservationReader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();

        $results = $this->race([
            ['operation' => 'checkout', 'actor_id' => $operator->id, 'reader_id' => $checkoutReader->id, 'subject_id' => $book->id],
            ['operation' => 'reserve', 'actor_id' => $reservationReader->id, 'reader_id' => $reservationReader->id, 'subject_id' => $book->id],
        ]);

        $this->assertNotContains('error', $results);
        $this->assertSame(1, Locacao::query()->whereNotNull('active_exemplar_id')->count()
            + Reserva::query()->whereNotNull('active_exemplar_id')->count());
        $this->assertSame(0, $book->fresh()->quantidade_disponivel);
    }

    public function test_competing_reservations_are_allocated_in_fifo_id_order_when_a_copy_returns(): void
    {
        $operator = User::factory()->librarian()->create();
        $borrower = User::factory()->reader()->create();
        $firstReader = User::factory()->reader()->create();
        $secondReader = User::factory()->reader()->create();
        [, , $book] = PhysicalCatalog::book();
        $loan = PhysicalCatalog::loan($borrower, $book);

        $results = $this->race([
            ['operation' => 'reserve', 'actor_id' => $firstReader->id, 'reader_id' => $firstReader->id, 'subject_id' => $book->id],
            ['operation' => 'reserve', 'actor_id' => $secondReader->id, 'reader_id' => $secondReader->id, 'subject_id' => $book->id],
        ]);

        $this->assertEqualsCanonicalizing(['created', 'created'], $results);
        app(CloseLoan::class)->return($operator, $loan);
        app(AllocateReservationsForBook::class)->execute($book->id);

        $reservations = Reserva::query()->where('livro_id', $book->id)->orderBy('id')->get();
        $this->assertCount(2, $reservations);
        $this->assertSame('disponivel', $reservations->first()->status->value);
        $this->assertSame('aguardando', $reservations->last()->status->value);
        $this->assertNotNull($reservations->first()->active_exemplar_id);
        $this->assertNull($reservations->last()->active_exemplar_id);
    }

    /** @param list<array{operation: string, actor_id: int, reader_id: int, subject_id: int}> $participants
     * @return array<int, string>
     */
    private function race(array $participants): array
    {
        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        $barrier = $directory.'/race-'.bin2hex(random_bytes(12));
        $processes = [];
        try {
            foreach ($participants as $index => $participant) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/circulation-race.php'), $participant['operation'],
                    (string) $participant['actor_id'], (string) $participant['reader_id'], (string) $participant['subject_id'], $barrier, (string) $index], base_path(), [
                        'BIBLIOTECA_TEST_DRIVER' => 'mysql', 'APP_ENV' => 'testing',
                        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
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
            $this->assertTrue($ready, 'Both independent database clients must reach the start barrier.');
            file_put_contents($barrier.'.go', 'go');
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), 'Concurrent child failed: '.$process->getOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['result'];
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
