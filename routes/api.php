<?php

use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\AutoNapJobController;
use App\Http\Controllers\Api\CppScrapeController;
use App\Http\Controllers\Api\HcodeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoRequestController;
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

Route::post('demo-request', [DemoRequestController::class, 'store']);

// OAuth2 client_credentials flow — external systems (e.g. ACTSE Clinic) use these
Route::post('auth/token', [ApiAuthController::class, 'issueToken'])
    ->middleware('throttle:20,1');
Route::post('auth/revoke', [ApiAuthController::class, 'revokeToken'])
    ->middleware('api.token');
Route::get('auth/me', [ApiAuthController::class, 'me'])
    ->middleware('api.token');

Route::post('test-callback', function (Request $request) {
    Log::info('Test callback received', $request->all());

    return response()->json([
        'status' => 'ok',
        'action' => 'logged',
        'received' => $request->all(),
    ]);
});
