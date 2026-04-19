<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Modules\Branch\Controllers\BranchController;
use App\Modules\RolePermission\Controllers\RolePermissionController;
use App\Modules\UserManagement\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — POSmeister
|--------------------------------------------------------------------------
| Prefix:     /api
| Auth guard: sanctum
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

    // ── Admin-only ────────────────────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {

        // Branches
        Route::get('branches/all',     [BranchController::class, 'all']);
        Route::apiResource('branches', BranchController::class);

        // Users
        Route::put('users/{user}/status', [UserController::class, 'toggleStatus']);
        Route::apiResource('users',       UserController::class);

        // Role permissions management
        Route::get('role-permissions',           [RolePermissionController::class, 'index']);
        Route::put('role-permissions/{role}',    [RolePermissionController::class, 'update']);
    });

    // ── Future module routes ──────────────────────────────────────────────
    // Route::apiResource('products',  ProductController::class);
    // Route::apiResource('customers', CustomerController::class);
    // Route::apiResource('sales',     SaleController::class);
});
