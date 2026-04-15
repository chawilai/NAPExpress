<?php

use App\Http\Controllers\Api\AutoNapJobController;
use App\Http\Controllers\Api\CppScrapeController;
use App\Http\Controllers\Api\HcodeController;
use App\Http\Controllers\DashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::post('jobs', [AutoNapJobController::class, 'store']);
Route::get('jobs/status', [AutoNapJobController::class, 'status']);
Route::get('dashboard', [DashboardController::class, 'api']);

Route::post('cpp-scrape/claim', [CppScrapeController::class, 'claim']);
Route::post('cpp-scrape/report', [CppScrapeController::class, 'report']);
Route::post('cpp-scrape/bulk-upsert', [CppScrapeController::class, 'bulkUpsert']);
Route::get('cpp-scrape/status', [CppScrapeController::class, 'status']);

Route::get('hcode/lookup', [HcodeController::class, 'lookup']);
Route::post('hcode/validate-bulk', [HcodeController::class, 'validateBulk']);

Route::post('test-callback', function (Request $request) {
    Log::info('Test callback received', $request->all());

    return response()->json([
        'status' => 'ok',
        'action' => 'logged',
        'received' => $request->all(),
    ]);
});
