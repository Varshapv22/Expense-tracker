<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\StatsController as AdminStatsController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DebtController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SummaryController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json(['ok' => true]);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('transactions', TransactionController::class);
    Route::apiResource('accounts', AccountController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('bills', BillController::class);
    Route::apiResource('debts', DebtController::class);
    Route::apiResource('subscriptions', SubscriptionController::class);
    Route::get('/expenses-summary', SummaryController::class);
    Route::get('/dashboard', DashboardController::class);
    Route::get('/budget', [BudgetController::class, 'show']);
    Route::post('/budget', [BudgetController::class, 'store']);

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/stats', AdminStatsController::class);
        Route::apiResource('users', AdminUserController::class)->only(['index', 'show', 'update', 'destroy']);
        Route::apiResource('categories', AdminCategoryController::class);
        Route::get('/audit-logs', [AdminAuditLogController::class, 'index']);
    });
});
