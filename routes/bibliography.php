<?php

use App\Http\Controllers\BibliographicLookupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'can:manage-library', 'request.context'])->group(function () {
    Route::get('integracoes/isbn', [BibliographicLookupController::class, 'index'])->name('isbn.index');
    Route::post('integracoes/isbn', [BibliographicLookupController::class, 'lookup'])->middleware('throttle:10,1')->name('isbn.lookup');
    Route::post('integracoes/isbn/usar', [BibliographicLookupController::class, 'useSuggestion'])->name('isbn.useSuggestion');
});
