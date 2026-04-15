<?php

use App\Http\Controllers\CppProviderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportingJobController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::inertia('/privacy', 'Legal/Privacy')->name('legal.privacy');
Route::inertia('/terms', 'Legal/Terms')->name('legal.terms');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [ReportingJobController::class, 'index'])->name('dashboard');

    Route::get('jobs/download-template', [ReportingJobController::class, 'downloadTemplate'])->name('jobs.download-template');
    Route::resource('jobs', ReportingJobController::class)->only(['index', 'store', 'show']);

    Route::get('cpp-providers', [CppProviderController::class, 'index'])->name('cpp-providers.index');
    Route::get('cpp-providers/{hcode}', [CppProviderController::class, 'show'])->name('cpp-providers.show');
});

// AutoNAP Dashboard (public, no auth)
Route::get('autonap', [DashboardController::class, 'index']);

require __DIR__.'/settings.php';
