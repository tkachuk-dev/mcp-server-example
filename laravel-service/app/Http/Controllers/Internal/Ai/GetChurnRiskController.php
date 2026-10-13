<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\Ai\GetChurnRiskRequest;
use App\Http\Resources\Ai\ChurnRiskResource;
use App\Services\Analytics\ChurnPredictionService;

class GetChurnRiskController extends Controller
{
    public function __construct(private readonly ChurnPredictionService $churn) {}

    public function getChurnRisk(GetChurnRiskRequest $request): ChurnRiskResource
    {
        return new ChurnRiskResource($this->churn->getChurnRisk($request->toDto()));
    }
}
