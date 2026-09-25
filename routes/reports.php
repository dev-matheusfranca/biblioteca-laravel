<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'request.context', 'can:manage-library'])
    ->prefix('relatorios')
    ->name('relatorios.')
    ->group(function (): void {
        Route::redirect('/', '/relatorios/circulacao')->name('index');
        Route::get('circulacao', [ReportController::class, 'circulation'])->name('circulacao');
        Route::get('circulacao.csv', [ReportController::class, 'circulationCsv'])->name('circulacao.csv');
        Route::get('fila', [ReportController::class, 'queue'])->name('fila');
        Route::get('fila.csv', [ReportController::class, 'queueCsv'])->name('fila.csv');
        Route::get('indisponiveis', [ReportController::class, 'unavailable'])->name('indisponiveis');
        Route::get('indisponiveis.csv', [ReportController::class, 'unavailableCsv'])->name('indisponiveis.csv');
    });
