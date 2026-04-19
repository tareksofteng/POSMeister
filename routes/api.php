<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Modules\Branch\Controllers\BranchController;
use App\Modules\UserManagement\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — POSmeister
|--------------------------------------------------------------------------
| Prefix:     /api
| Auth guard: sanctum
|
| Middleware stack for protected routes:
|   auth:sanctum → validates Bearer token
|   branch       → sets pos.activeBranchId for BranchScoped trait
*/

// ── Public ────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

// ── Protected ─────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'branch'])->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::get('me',      [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });

    // ── Branches (admin only) ─────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::get('branches/all',        [BranchController::class, 'all']);    // dropdown list
        Route::apiResource('branches',    BranchController::class);
    });

    // ── Users (admin only) ────────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::put('users/{user}/status', [UserController::class, 'toggleStatus']);
        Route::apiResource('users',       UserController::class);
    });

    // ── Future module routes ──────────────────────────────────────────
    // Route::apiResource('products',  ProductController::class);
    // Route::apiResource('customers', CustomerController::class);
    // Route::apiResource('sales',     SaleController::class);
    // Route::apiResource('purchases', PurchaseController::class);
});
