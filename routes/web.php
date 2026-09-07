<?php

declare(strict_types=1);

use App\Http\Controllers\Dev\ConsoleController;
use App\Http\Middleware\DevConsoleOnly;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'status' => 'ok',
]));

/*
 * Development console. The middleware refuses everything in production, so
 * these routes exist but never answer there.
 */
Route::middleware(DevConsoleOnly::class)->prefix('dev')->name('dev.')->group(function (): void {
    Route::get('/', [ConsoleController::class, 'page'])->name('console');
    Route::get('state', [ConsoleController::class, 'state'])->name('state');
    Route::post('prices', [ConsoleController::class, 'pushPrices'])->name('prices');
    Route::post('alerts', [ConsoleController::class, 'createAlert'])->name('alerts.store');
    Route::delete('alerts/{alert}', [ConsoleController::class, 'deleteAlert'])->name('alerts.destroy');
    Route::post('inbox/clear', [ConsoleController::class, 'clearInbox'])->name('inbox.clear');
    Route::post('reset', [ConsoleController::class, 'reset'])->name('reset');
});
