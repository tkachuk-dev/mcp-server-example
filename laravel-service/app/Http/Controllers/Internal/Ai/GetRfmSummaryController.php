<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\Ai\GetRfmSummaryRequest;
use App\Http\Resources\Ai\RfmSummaryResource;
use App\Services\Analytics\RfmSegmentationService;

class GetRfmSummaryController extends Controller
{
    public function __construct(private readonly RfmSegmentationService $rfm) {}

    public function getRfmSummary(GetRfmSummaryRequest $request): RfmSummaryResource
    {
        return new RfmSummaryResource($this->rfm->getSummary($request->toDto()));
    }
}
