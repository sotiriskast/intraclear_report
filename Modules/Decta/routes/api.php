<?php

use Illuminate\Support\Facades\Route;
use Modules\Decta\Http\Controllers\Api\DectaSftpController;

Route::middleware(['auth:sanctum'])->prefix('api/decta/sftp')->group(function () {
    // SFTP File Operations
    Route::get('/files', [DectaSftpController::class, 'listFiles']);
    Route::post('/download', [DectaSftpController::class, 'download']);
    Route::post('/process', [DectaSftpController::class, 'processFile']);
    Route::post('/download-and-process', [DectaSftpController::class, 'downloadAndProcess']);
});
