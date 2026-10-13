<?php

declare(strict_types=1);

namespace App\Http\Resources\Ai;

use App\Dto\Ai\ChurnRiskDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ChurnRiskDto $resource
 */
class ChurnRiskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tenant_id' => $this->resource->tenantId,
            'limit' => $this->resource->limit,
            'generated_at' => $this->resource->generatedAt->toIso8601String(),
            'customers' => ChurnCustomerResource::collection($this->resource->customers),
        ];
    }
}
