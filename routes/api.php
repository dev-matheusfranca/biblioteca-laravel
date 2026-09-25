<?php

use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CirculationController;
use App\Http\Controllers\Api\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['request.context', 'throttle:60,1'])->group(function () {
    Route::get('catalogo', [CatalogController::class, 'index'])->name('api.v1.catalogo.index');
    Route::get('catalogo/{livro}', [CatalogController::class, 'show'])->name('api.v1.catalogo.show');

    Route::middleware('auth:sanctum')->group(function () {
        Route::middleware('api.token:personal:read')->group(function () {
            Route::get('me', [MeController::class, 'show'])->name('api.v1.me.show');
            Route::get('me/emprestimos', [MeController::class, 'loans'])->name('api.v1.me.loans');
            Route::get('me/emprestimos/{locacao}', [MeController::class, 'loan'])->name('api.v1.me.loans.show');
            Route::get('me/reservas', [MeController::class, 'reservations'])->name('api.v1.me.reservations');
        });

        Route::post('livros/{livro}/reservas', [CirculationController::class, 'reserve'])
            ->middleware(['api.token:reservations:write', 'throttle:20,1', 'idempotency'])
            ->name('api.v1.reservations.store');
        Route::delete('reservas/{reserva}', [CirculationController::class, 'cancel'])
            ->middleware(['api.token:reservations:write', 'throttle:20,1', 'idempotency'])
            ->name('api.v1.reservations.destroy');
        Route::post('emprestimos/{locacao}/renovacoes', [CirculationController::class, 'renew'])
            ->middleware(['api.token:loans:renew', 'throttle:20,1', 'idempotency'])
            ->name('api.v1.loans.renew');
    });
});
