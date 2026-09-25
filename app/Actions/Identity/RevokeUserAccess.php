<?php

namespace App\Actions\Identity;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RevokeUserAccess
{
    public function execute(User $user): void
    {
        $user->tokens()->delete();
        if (config('session.driver') === 'database') {
            $configuredConnection = config('session.connection');
            $connection = is_string($configuredConnection) && $configuredConnection !== ''
                ? $configuredConnection
                : null;
            DB::connection($connection)
                ->table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }
    }
}
