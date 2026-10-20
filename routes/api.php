<?php

use App\Http\Controllers\Api\DiscoveryController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\ScrapeJobController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\ExportController as WebExportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('tokens', [TokenController::class, 'store'])->middleware('throttle:6,1')->name('api.tokens.store');

Route::middleware(['auth:sanctum', 'throttle:api'])->name('api.')->group(function () {
    Route::get('user', fn (Request $request) => $request->user())->name('user');
    Route::delete('tokens/current', [TokenController::class, 'destroy'])->name('tokens.destroy');

    Route::post('discoveries', [DiscoveryController::class, 'store'])->middleware('throttle:discoveries')->name('discoveries.store');
    Route::get('discoveries/{discovery}', [DiscoveryController::class, 'show'])->name('discoveries.show');
    Route::post('discoveries/{discovery}/extract', [DiscoveryController::class, 'extract'])->name('discoveries.extract');

    Route::get('jobs/{job}', [ScrapeJobController::class, 'show'])->name('jobs.show');
    Route::get('jobs/{job}/products', [ScrapeJobController::class, 'products'])->name('jobs.products');
    Route::post('jobs/{job}/images/retry', [ScrapeJobController::class, 'retryImages'])->name('jobs.images.retry');

    Route::post('exports', [ExportController::class, 'store'])->middleware('throttle:exports')->name('exports.store');
    Route::get('exports/{export}', [ExportController::class, 'show'])->name('exports.show');
});

Route::get('exports/{export}/download', [WebExportController::class, 'download'])->middleware('signed')->name('api.exports.download');
