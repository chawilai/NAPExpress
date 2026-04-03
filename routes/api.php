<?php

use App\Http\Controllers\Api\AutoNapJobController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::post('jobs', [AutoNapJobController::class, 'store']);

Route::post('test-callback', function (Request $request) {
    Log::info('Test callback received', $request->all());

    return response()->json([
        'status' => 'ok',
        'action' => 'logged',
        'received' => $request->all(),
    ]);
});
