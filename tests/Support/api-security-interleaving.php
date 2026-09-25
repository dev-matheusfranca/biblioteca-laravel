<?php

use App\Actions\Api\ExecuteIdempotentMutation;
use App\Actions\Api\IssuePersonalAccessToken;
use App\Actions\Circulation\CreateReservation;
use App\Actions\Identity\RevokeUserAccess;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
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

[$script, $operation, $readerId, $subjectId, $key, $barrier] = $argv;
if (! str_starts_with($barrier, storage_path('framework/testing/api-security-'))) {
    exit(92);
}

function waitForMarker(string $marker): void
{
    $deadline = microtime(true) + 15;
    while (! is_file($marker)) {
        if (microtime(true) > $deadline) {
            exit(93);
        }
        usleep(10000);
    }
}

try {
    if ($operation === 'mutation-after-revoke') {
        $token = PersonalAccessToken::query()->findOrFail((int) $subjectId);
        $reader = User::query()->findOrFail((int) $readerId)->withAccessToken($token);
        $request = Request::create("/api/v1/livros/{$key}/reservas", 'POST', server: [
            'HTTP_IDEMPOTENCY_KEY' => '11111111-1111-4111-8111-111111111111',
        ]);
        $request->attributes->set('api_required_ability', 'reservations:write');
        file_put_contents($barrier.'.authenticated', 'ready');
        waitForMarker($barrier.'.revoked');

        try {
            app(ExecuteIdempotentMutation::class)->execute(
                $request,
                $reader,
                function (User $lockedReader) use ($key): array {
                    $reservation = app(CreateReservation::class)->execute($lockedReader, (int) $key);

                    return ['status' => 201, 'body' => ['data' => ['id' => $reservation->id]]];
                },
            );
            echo json_encode(['result' => 'committed'], JSON_THROW_ON_ERROR);
        } catch (AuthorizationException) {
            echo json_encode(['result' => 'rejected'], JSON_THROW_ON_ERROR);
        }
    } elseif ($operation === 'revoke-after-auth') {
        waitForMarker($barrier.'.authenticated');
        PersonalAccessToken::query()->whereKey((int) $subjectId)->delete();
        file_put_contents($barrier.'.revoked', 'done');
        echo json_encode(['result' => 'revoked'], JSON_THROW_ON_ERROR);
    } elseif ($operation === 'issue-after-reset') {
        $reader = User::query()->findOrFail((int) $readerId);
        if (! Hash::check('password', $reader->password)) {
            exit(95);
        }
        file_put_contents($barrier.'.prevalidated', 'ready');
        waitForMarker($barrier.'.reset');

        try {
            app(IssuePersonalAccessToken::class)->execute(
                $reader,
                'password',
                'Token concorrente',
                ['personal:read'],
            );
            echo json_encode(['result' => 'issued'], JSON_THROW_ON_ERROR);
        } catch (ValidationException) {
            echo json_encode(['result' => 'rejected'], JSON_THROW_ON_ERROR);
        }
    } elseif ($operation === 'reset-after-validation') {
        waitForMarker($barrier.'.prevalidated');
        DB::transaction(function () use ($readerId): void {
            $reader = User::query()->lockForUpdate()->findOrFail((int) $readerId);
            $reader->forceFill(['password' => Hash::make('nova-senha-segura')])->save();
            app(RevokeUserAccess::class)->execute($reader);
        }, 3);
        file_put_contents($barrier.'.reset', 'done');
        echo json_encode(['result' => 'reset'], JSON_THROW_ON_ERROR);
    } else {
        exit(94);
    }
} catch (Throwable $exception) {
    echo json_encode(['result' => 'error', 'type' => $exception::class], JSON_THROW_ON_ERROR);
    exit(96);
}
