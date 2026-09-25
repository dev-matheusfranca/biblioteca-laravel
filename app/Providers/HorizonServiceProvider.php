<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function authorization(): void
    {
        Gate::define('viewHorizon', fn (User $user): bool => $user->isAdmin());
        // The dashboard has the same authorization in local and deployed environments.
        Horizon::auth(fn (Request $request): bool => $request->user()?->isAdmin() ?? false);
    }
}
