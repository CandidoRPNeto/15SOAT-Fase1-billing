<?php

use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\OfflinePaymentController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Middleware\TrustGatewayIdentity;
use Illuminate\Support\Facades\Route;

// Every route sits behind Traefik ForwardAuth (ADR-013).
Route::prefix('v1')->middleware(TrustGatewayIdentity::class)->group(function () {
    Route::get('/budgets', [BudgetController::class, 'index']);
    Route::get('/budgets/{id}', [BudgetController::class, 'show']);
    Route::middleware('role:client')->group(function () {
        Route::post('/budgets/{id}/approve', [BudgetController::class, 'approve']);
        Route::post('/budgets/{id}/reject', [BudgetController::class, 'reject']);
    });

    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/offline/{reference}/approve', [OfflinePaymentController::class, 'approve']);
    Route::get('/payments/{id}', [PaymentController::class, 'show']);
    Route::post('/payments/{id}/verify', [PaymentController::class, 'verify']);
});
