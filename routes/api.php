<?php

use App\Http\Controllers\Api\HybridSyncController;
use App\Http\Middleware\AuthenticateHybridDevice;
use Illuminate\Support\Facades\Route;

/*
| Hybrid offline/online sync APIs (device token via Bearer / X-Hybrid-Token)
*/

Route::prefix('hybrid')->group(function () {
    Route::middleware([AuthenticateHybridDevice::class])->group(function () {
        Route::get('/health', [HybridSyncController::class, 'health']);
    });

    Route::middleware([AuthenticateHybridDevice::class.':attendance'])->group(function () {
        Route::get('/attendance/roster', [HybridSyncController::class, 'attendanceRoster']);
        Route::post('/attendance/scans', [HybridSyncController::class, 'pushAttendance']);
    });

    Route::middleware([AuthenticateHybridDevice::class.':opac'])->group(function () {
        Route::get('/opac/snapshot', [HybridSyncController::class, 'opacSnapshot']);
    });

    Route::middleware([AuthenticateHybridDevice::class.':catalog'])->group(function () {
        Route::post('/catalog/records', [HybridSyncController::class, 'pushCatalog']);
    });
});
