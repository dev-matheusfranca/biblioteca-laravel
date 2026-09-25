<?php

use App\Actions\Circulation\CheckoutExemplar;
use App\Actions\Circulation\CloseLoan;
use App\Actions\Circulation\CreateReservation;
use App\Actions\Circulation\RenewLoan;
use App\Models\Locacao;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

// Dedicated MySQL test process; never boot the operational configuration.
putenv('BIBLIOTECA_TEST_DRIVER=mysql');
require __DIR__.'/../bootstrap.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'biblioteca_testing'
    || config('database.connections.mysql.username') !== 'biblioteca_test') {
    exit(91);
}

[$script, $operation, $actorId, $readerId, $subjectId, $barrier, $participant] = $argv;
if (! str_starts_with($barrier, storage_path('framework/testing/race-'))) {
    exit(92);
}
file_put_contents($barrier.'.'.$participant.'.ready', 'ready');
$deadline = microtime(true) + 15;
while (! is_file($barrier.'.go')) {
    if (microtime(true) > $deadline) {
        exit(93);
    }
    usleep(10000);
}
try {
    $actor = User::findOrFail((int) $actorId);
    if ($operation === 'checkout') {
        $loan = app(CheckoutExemplar::class)->execute(
            $actor, (int) $readerId, (int) $subjectId, now()->addDays(14)->toDateString()
        );
        echo json_encode(['result' => 'created', 'id' => $loan->id]);
    } elseif ($operation === 'return') {
        $changed = app(CloseLoan::class)->return($actor, Locacao::findOrFail((int) $subjectId));
        echo json_encode(['result' => $changed ? 'changed' : 'unchanged']);
    } elseif ($operation === 'renew') {
        $renewal = app(RenewLoan::class)->execute($actor, Locacao::findOrFail((int) $subjectId));
        echo json_encode(['result' => 'created', 'id' => $renewal->id]);
    } elseif ($operation === 'reserve') {
        $reservation = app(CreateReservation::class)->execute($actor, (int) $subjectId);
        echo json_encode(['result' => 'created', 'id' => $reservation->id]);
    } else {
        exit(94);
    }
} catch (DomainException $exception) {
    echo json_encode(['result' => 'rejected']);
} catch (Throwable $exception) {
    // Expose the exception type for diagnosis, never connection details or data.
    echo json_encode(['result' => 'error', 'type' => $exception::class]);
    exit(95);
}
