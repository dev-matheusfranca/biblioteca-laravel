<?php

use App\Http\Controllers\OperationsHealthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'can:manage-team', 'request.context'])->group(function () {
    Route::get('operacao/saude', OperationsHealthController::class)->name('operacao.saude');
});
