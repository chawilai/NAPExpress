<?php

use App\Http\Controllers\ReportingJobController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [ReportingJobController::class, 'index'])->name('dashboard');
    
    Route::get('jobs/download-template', [ReportingJobController::class, 'downloadTemplate'])->name('jobs.download-template');
    Route::resource('jobs', ReportingJobController::class)->only(['index', 'store', 'show']);
});

require __DIR__.'/settings.php';
