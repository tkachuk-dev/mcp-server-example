<?php

declare(strict_types=1);

use App\Http\Controllers\Health\GetLivenessController;
use App\Http\Controllers\Health\GetReadinessController;
use Illuminate\Support\Facades\Route;

Route::get('/health/live', [GetLivenessController::class, 'getLiveness'])->name('health.getLiveness');
Route::get('/health/ready', [GetReadinessController::class, 'getReadiness'])->name('health.getReadiness');
