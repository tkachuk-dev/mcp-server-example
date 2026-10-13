<?php

declare(strict_types=1);

use App\Http\Controllers\Internal\Ai\GetChurnRiskController;
use App\Http\Controllers\Internal\Ai\GetRevenueSummaryController;
use App\Http\Controllers\Internal\Ai\GetRfmSummaryController;
use Illuminate\Support\Facades\Route;

// Loaded in bootstrap/app.php with prefix internal/ai/v1 and the internal_ai middleware group (no "web").
Route::get('/tenants/{tenant}/rfm-summary', [GetRfmSummaryController::class, 'getRfmSummary'])->name('getRfmSummary');
Route::get('/tenants/{tenant}/churn-risk', [GetChurnRiskController::class, 'getChurnRisk'])->name('getChurnRisk');
Route::get('/tenants/{tenant}/revenue-summary', [GetRevenueSummaryController::class, 'getRevenueSummary'])->name('getRevenueSummary');
