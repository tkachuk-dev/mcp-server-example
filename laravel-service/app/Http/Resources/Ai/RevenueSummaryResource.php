<?php

declare(strict_types=1);

namespace App\Http\Resources\Ai;

use App\Dto\Ai\RevenueSummaryDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property RevenueSummaryDto $resource
 */
class RevenueSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tenant_id' => $this->resource->tenantId,
            'days' => $this->resource->days,
            'currency' => $this->resource->currency,
            'period_start' => $this->resource->periodStart->toDateString(),
            'period_end' => $this->resource->periodEnd->toDateString(),
            'generated_at' => $this->resource->generatedAt->toIso8601String(),
            'daily' => RevenueDayResource::collection($this->resource->daily),
        ];
    }
}
