<?php

use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\ApiRollingReserveController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->group(function () {
    // Public routes
    Route::post('/auth/token', [ApiTokenController::class, 'createToken']);

    // Protected routes
    Route::middleware(['api.auth'])->group(function () {
        // Rolling Reserve endpoints
        Route::prefix('rolling-reserves')->group(function () {
            Route::get('/', [ApiRollingReserveController::class, 'index']);
            Route::get('/summary', [ApiRollingReserveController::class, 'summary']);
        });

        // Token management
        Route::post('/auth/token/revoke', [ApiTokenController::class, 'revokeToken']);
    });

    // Catch-all route for undefined API endpoints
    Route::fallback(function () {
        return response()->json([
            'error' => true,
            'message' => 'API endpoint not found',
            'status_code' => 404
        ], 404);
    });
});
