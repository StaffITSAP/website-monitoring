<?php

use App\Http\Controllers\MonitoringController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/monitoring-data', [MonitoringController::class, 'getMonitoringData'])
    ->name('monitoring.data');

Route::get('/download-pdf', [MonitoringController::class, 'downloadPDF'])
    ->name('monitoring.download.pdf');
