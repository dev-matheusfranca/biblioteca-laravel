<?php

namespace App\Providers;

use App\Models\Autor;
use App\Models\Categoria;
use App\Models\Livro;
use App\Observers\CatalogObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('pagination.biblioteca');
        Livro::observe(CatalogObserver::class);
        Autor::observe(CatalogObserver::class);
        Categoria::observe(CatalogObserver::class);

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            mb_strtolower((string) $request->input('email')).'|'.$request->ip()
        ));
        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        Gate::define('manage-library', fn ($user) => $user->isStaff());
        Gate::define('manage-team', fn ($user) => $user->isAdmin());
        Gate::define('manage-users', fn ($user) => $user->isAdmin());
    }
}
