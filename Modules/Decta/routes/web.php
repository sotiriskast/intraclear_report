<?php

use Illuminate\Support\Facades\Route;
use Modules\Decta\Http\Controllers\DectaController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('dectas', DectaController::class)->names('decta');
});
