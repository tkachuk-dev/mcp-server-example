<?php

declare(strict_types=1);

namespace App\Http\Resources\Ai;

use App\Dto\Ai\ChurnCustomerDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ChurnCustomerDto $resource
 */
class ChurnCustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->resource->userId,
            'churn_score' => $this->resource->churnScore,
            'segment' => $this->resource->segment->value,
            'last_order_at' => $this->resource->lastOrderAt?->toIso8601String(),
        ];
    }
}
