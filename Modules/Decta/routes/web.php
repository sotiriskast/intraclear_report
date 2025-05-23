<?php

use Illuminate\Support\Facades\Route;
use Modules\Decta\Http\Controllers\DectaController;
use Modules\Decta\Http\Controllers\DectaSftpViewController;

Route::middleware(['auth', 'verified'])->prefix('decta')->group(function () {
    Route::get('/', [DectaController::class, 'index'])->name('decta.index');

    // SFTP Management Routes
    Route::prefix('sftp')->group(function () {
        Route::get('/', [DectaSftpViewController::class, 'index'])->name('decta.sftp.index');
        Route::get('/list', [DectaSftpViewController::class, 'listFiles'])->name('decta.sftp.list-files');
        Route::post('/download', [DectaSftpViewController::class, 'download'])->name('decta.sftp.download');
        Route::post('/process', [DectaSftpViewController::class, 'process'])->name('decta.sftp.process');
        Route::get('/download-file', [DectaSftpViewController::class, 'downloadFile'])->name('decta.sftp.download-file');
    });
});
