<?php

declare(strict_types=1);

namespace App\Http\Resources\Ai;

use App\Dto\Ai\RfmSegmentSummaryDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property RfmSegmentSummaryDto $resource
 */
class RfmSegmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'segment' => $this->resource->segment->value,
            'customers_count' => $this->resource->customersCount,
            'avg_recency_days' => $this->resource->avgRecencyDays,
            'avg_frequency' => $this->resource->avgFrequency,
            'avg_monetary_minor' => $this->resource->avgMonetaryMinor,
        ];
    }
}
