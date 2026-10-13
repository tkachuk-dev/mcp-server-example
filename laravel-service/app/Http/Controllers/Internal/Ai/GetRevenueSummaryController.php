<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\Ai\GetRevenueSummaryRequest;
use App\Http\Resources\Ai\RevenueSummaryResource;
use App\Services\Analytics\RevenueReportService;

class GetRevenueSummaryController extends Controller
{
    public function __construct(private readonly RevenueReportService $revenue) {}

    public function getRevenueSummary(GetRevenueSummaryRequest $request): RevenueSummaryResource
    {
        return new RevenueSummaryResource($this->revenue->getRevenueSummary($request->toDto()));
    }
}
