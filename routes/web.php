<?php

use App\Http\Controllers\DiscoveryController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ScrapeJobController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Fortify sends users to /dashboard after login; Discover is the app's start page.
    Route::redirect('dashboard', '/discover')->name('dashboard');

    Route::get('discover', [DiscoveryController::class, 'index'])->name('discover');
    Route::post('discoveries', [DiscoveryController::class, 'store'])->middleware('throttle:discoveries')->name('discoveries.store');
    Route::get('discoveries/{discovery}', [DiscoveryController::class, 'show'])->name('discoveries.show');
    Route::post('discoveries/{discovery}/extract', [DiscoveryController::class, 'extract'])->name('discoveries.extract');
    Route::post('discoveries/{discovery}/rediscover', [DiscoveryController::class, 'rediscover'])->middleware('throttle:discoveries')->name('discoveries.rediscover');
    Route::delete('discoveries/{discovery}', [DiscoveryController::class, 'destroy'])->name('discoveries.destroy');

    Route::get('jobs', [ScrapeJobController::class, 'index'])->name('jobs.index');
    Route::get('jobs/{job}', [ScrapeJobController::class, 'show'])->name('jobs.show');
    Route::post('jobs/{job}/rerun', [ScrapeJobController::class, 'rerun'])->name('jobs.rerun');
    Route::post('jobs/{job}/images/retry', [ScrapeJobController::class, 'retryImages'])->name('jobs.images.retry');
    Route::delete('jobs/{job}', [ScrapeJobController::class, 'destroy'])->name('jobs.destroy');

    Route::get('exports', [ExportController::class, 'index'])->name('exports.index');
    Route::post('exports', [ExportController::class, 'store'])->middleware('throttle:exports')->name('exports.store');
    Route::delete('exports/{export}', [ExportController::class, 'destroy'])->name('exports.destroy');

    Route::get('sites', [SiteController::class, 'index'])->name('sites.index');
    Route::get('sites/{site}/recipe', [RecipeController::class, 'edit'])->name('sites.recipe.edit');
    Route::get('sites/{site}/recipe/frame', [RecipeController::class, 'frame'])->name('sites.recipe.frame');
    Route::post('sites/{site}/recipe/preview', [RecipeController::class, 'preview'])->name('sites.recipe.preview');
    Route::put('sites/{site}/recipe', [RecipeController::class, 'update'])->name('sites.recipe.update');
});

// The signature (24 hours) is the permission, so the link also works from emails and API clients.
Route::get('exports/{export}/download', [ExportController::class, 'download'])->middleware('signed')->name('exports.download');

require __DIR__.'/settings.php';
