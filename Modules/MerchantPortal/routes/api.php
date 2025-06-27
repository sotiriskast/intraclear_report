<?php

use Illuminate\Support\Facades\Route;
use Modules\MerchantPortal\Http\Controllers\MerchantPortalController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('merchantportals', MerchantPortalController::class)->names('merchantportal');
});
