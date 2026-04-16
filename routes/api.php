<?php

use App\Http\Controllers\Api\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — POSmeister
|--------------------------------------------------------------------------
| Prefix:     /api
| Auth guard: sanctum
*/

// ── Public ───────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

// ── Protected (requires valid Sanctum token) ─────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });

    // Future module routes will be added here:
    // Route::apiResource('products',  ProductController::class);
    // Route::apiResource('customers', CustomerController::class);
    // ...
});
