<?php

declare(strict_types=1);

namespace App\Http\Resources\Ai;

use App\Dto\Ai\RfmSummaryDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property RfmSummaryDto $resource
 */
class RfmSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tenant_id' => $this->resource->tenantId,
            'currency' => $this->resource->currency,
            'generated_at' => $this->resource->generatedAt->toIso8601String(),
            'segments' => RfmSegmentResource::collection($this->resource->segments),
        ];
    }
}
