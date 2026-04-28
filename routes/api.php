<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Modules\Branch\Controllers\BranchController;
use App\Modules\Product\Controllers\BrandController;
use App\Modules\Purchase\Controllers\PurchaseReturnController;
use App\Modules\Purchase\Controllers\SupplierPaymentController;
use App\Modules\Sales\Controllers\CustomerController;
use App\Modules\Sales\Controllers\CustomerPaymentController;
use App\Modules\Sales\Controllers\SaleController;
use App\Modules\Sales\Controllers\SaleReturnController;
use App\Modules\Stock\Controllers\StockController;
use App\Modules\Product\Controllers\CategoryController;
use App\Modules\Product\Controllers\ProductController;
use App\Modules\Product\Controllers\UnitController;
use App\Modules\Purchase\Controllers\PurchaseController;
use App\Modules\Purchase\Controllers\SupplierController;
use App\Modules\RolePermission\Controllers\RolePermissionController;
use App\Modules\Settings\Controllers\SettingsController;
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

    // Dashboard stats
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);

    // ── Admin-only ────────────────────────────────────────────────────────
    Route::middleware('role:admin')->group(function ()
    {
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

    // ── Settings ──────────────────────────────────────────────────────────
    Route::get('settings', [SettingsController::class, 'show']);

    Route::middleware('role:admin')->group(function () {
        Route::put('settings',              [SettingsController::class, 'update']);
        Route::post('settings/logo',        [SettingsController::class, 'uploadLogo']);
        Route::delete('settings/logo',      [SettingsController::class, 'deleteLogo']);
    });

    // ── Product Module ────────────────────────────────────────────────────
    // Units — read by all authenticated users (needed in POS terminal)
    Route::get('units',     [UnitController::class, 'index']);
    Route::get('units/all', [UnitController::class, 'all']);

    // Categories & Brands — read by all, write by admin+manager
    Route::get('categories',     [CategoryController::class, 'index']);
    Route::get('categories/all', [CategoryController::class, 'all']);
    Route::get('brands',         [BrandController::class, 'index']);
    Route::get('brands/all',     [BrandController::class, 'all']);

    // Products — read by all, write by admin+manager
    Route::get('products/all',    [ProductController::class, 'all']);
    Route::get('products/search', [ProductController::class, 'search']);
    Route::get('products',        [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);

    Route::middleware('role:admin,manager')->group(function () {
        // Categories
        Route::post('categories',              [CategoryController::class, 'store']);
        Route::put('categories/{category}',    [CategoryController::class, 'update']);
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

        // Brands
        Route::post('brands',          [BrandController::class, 'store']);
        Route::put('brands/{brand}',   [BrandController::class, 'update']);
        Route::delete('brands/{brand}',[BrandController::class, 'destroy']);

        // Units
        Route::post('units',         [UnitController::class, 'store']);
        Route::put('units/{unit}',   [UnitController::class, 'update']);
        Route::delete('units/{unit}',[UnitController::class, 'destroy']);

        // Products
        Route::post('products',                        [ProductController::class, 'store']);
        Route::put('products/{product}',               [ProductController::class, 'update']);
        Route::put('products/{product}/status',        [ProductController::class, 'toggleStatus']);
        Route::delete('products/{product}',            [ProductController::class, 'destroy']);
        Route::post('products/{product}/image',        [ProductController::class, 'uploadImage']);
        Route::delete('products/{product}/image',      [ProductController::class, 'deleteImage']);
    });

    // ── Sales / POS ──────────────────────────────────────────────────────
    // POS product search (all authenticated users)
    Route::get('pos/products',            [SaleController::class, 'posSearch']);

    // Customers — read by all, write by admin+manager+cashier
    Route::get('customers/all',                              [CustomerController::class, 'all']);
    Route::get('customers',                                  [CustomerController::class, 'index']);
    Route::get('customers/{customer}',                       [CustomerController::class, 'show']);
    Route::post('customers',                                 [CustomerController::class, 'store']);
    Route::put('customers/{customer}',                       [CustomerController::class, 'update']);
    Route::get('customers/{customer}/payments',              [CustomerController::class, 'payments']);
    Route::post('customers/{customer}/payments',             [CustomerController::class, 'storePayment']);

    // ── Customer Payments (standalone — global list) ──────────────────────
    Route::get('customer-payments',        [CustomerPaymentController::class, 'index']);
    Route::post('customer-payments',       [CustomerPaymentController::class, 'store']);
    Route::get('customer-payments/{id}',   [CustomerPaymentController::class, 'show']);

    // Sales — list/show by all, create by cashier+, cancel by manager+
    Route::get('sales/record',            [SaleController::class, 'record']); // must be before {sale}
    Route::get('sales',                   [SaleController::class, 'index']);
    Route::get('sales/{sale}',            [SaleController::class, 'show']);
    Route::post('sales',                  [SaleController::class, 'store']);
    Route::put('sales/{sale}/cancel',     [SaleController::class, 'cancel']);

    // ── Stock / Inventory ─────────────────────────────────────────────────
    Route::get('stock/filter-options', [StockController::class, 'filterOptions']);
    Route::get('stock/current',        [StockController::class, 'current']);

    // ── Purchase Module ───────────────────────────────────────────────────
    // Suppliers — read by all authenticated, write by admin+manager
    Route::get('suppliers/all', [SupplierController::class, 'all']);
    Route::get('suppliers',     [SupplierController::class, 'index']);
    Route::get('suppliers/{supplier}', [SupplierController::class, 'show']);

    Route::middleware('role:admin,manager')->group(function () {
        Route::post('suppliers',                         [SupplierController::class, 'store']);
        Route::put('suppliers/{supplier}',               [SupplierController::class, 'update']);
        Route::put('suppliers/{supplier}/status',        [SupplierController::class, 'toggleStatus']);
        Route::delete('suppliers/{supplier}',            [SupplierController::class, 'destroy']);

        // Purchases
        Route::get('purchases',                       [PurchaseController::class, 'index']);
        Route::get('purchases/record',                [PurchaseController::class, 'record']); // must be before {purchase}
        Route::get('purchases/{purchase}',            [PurchaseController::class, 'show']);
        Route::post('purchases',                      [PurchaseController::class, 'store']);
        Route::put('purchases/{purchase}',            [PurchaseController::class, 'update']);
        Route::put('purchases/{purchase}/receive',    [PurchaseController::class, 'receive']);
        Route::delete('purchases/{purchase}',         [PurchaseController::class, 'destroy']);

        // ── Supplier Payments ─────────────────────────────────────────────
        Route::get('supplier-payments',        [SupplierPaymentController::class, 'index']);
        Route::post('supplier-payments',       [SupplierPaymentController::class, 'store']);
        Route::get('supplier-payments/{id}',   [SupplierPaymentController::class, 'show']);

        // Purchase Returns
        Route::get('purchase-returns/record',                         [PurchaseReturnController::class, 'record']); // must be before {id}
        Route::get('purchase-returns',                                [PurchaseReturnController::class, 'index']);
        Route::get('purchase-returns/{id}',                          [PurchaseReturnController::class, 'show']);
        Route::get('purchases/{purchaseId}/return-details',           [PurchaseReturnController::class, 'returnDetails']);
        Route::post('purchase-returns',                               [PurchaseReturnController::class, 'store']);

        // Sale Returns
        Route::get('sale-returns/record',                             [SaleReturnController::class, 'record']); // must be before {id}
        Route::get('sale-returns',                                    [SaleReturnController::class, 'index']);
        Route::get('sale-returns/{id}',                              [SaleReturnController::class, 'show']);
        Route::get('sales/{saleId}/return-details',                   [SaleReturnController::class, 'returnDetails']);
        Route::post('sale-returns',                                   [SaleReturnController::class, 'store']);
    });
});
