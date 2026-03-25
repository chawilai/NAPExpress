<?php

use App\Http\Controllers\Api\AutoNapJobController;
use Illuminate\Support\Facades\Route;

Route::post('jobs', [AutoNapJobController::class, 'store']);
