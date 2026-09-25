<?php

use App\Actions\Api\ExecuteIdempotentMutation;
use App\Actions\Circulation\CreateReservation;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

// Dedicated MySQL test process; never boot the operational configuration.
putenv('BIBLIOTECA_TEST_DRIVER=mysql');
require __DIR__.'/../bootstrap.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'biblioteca_testing'
    || config('database.connections.mysql.username') !== 'biblioteca_test') {
    exit(91);
}

[$script, $readerId, $tokenId, $bookId, $key, $barrier, $participant] = $argv;
if (! str_starts_with($barrier, storage_path('framework/testing/api-race-'))) {
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
    $token = PersonalAccessToken::query()->findOrFail((int) $tokenId);
    $reader = User::query()->findOrFail((int) $readerId)->withAccessToken($token);
    $request = Request::create("/api/v1/livros/{$bookId}/reservas", 'POST', server: [
        'HTTP_IDEMPOTENCY_KEY' => $key,
    ]);
    $request->attributes->set('api_required_ability', 'reservations:write');

    $result = app(ExecuteIdempotentMutation::class)->execute(
        $request,
        $reader,
        function (User $lockedReader) use ($bookId): array {
            $reservation = app(CreateReservation::class)->execute($lockedReader, (int) $bookId);

            return ['status' => 201, 'body' => ['data' => ['id' => $reservation->id]]];
        },
    );

    echo json_encode([
        'result' => $result->replayed ? 'replayed' : 'created',
        'id' => $result->body['data']['id'],
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    // Expose only the exception type so the concurrent harness cannot leak data.
    echo json_encode(['result' => 'error', 'type' => $exception::class], JSON_THROW_ON_ERROR);
    exit(94);
}
