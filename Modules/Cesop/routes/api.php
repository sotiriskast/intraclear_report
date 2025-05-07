<?php

use Illuminate\Support\Facades\Route;
use Modules\Cesop\Http\Controllers\CesopController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('cesop', CesopController::class)->names('cesop');
});
