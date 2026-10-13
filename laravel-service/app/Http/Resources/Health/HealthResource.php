<?php

declare(strict_types=1);

namespace App\Http\Resources\Health;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CarbonImmutable $resource
 */
class HealthResource extends JsonResource
{
    public const string SERVICE_NAME = 'billing-service';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'service' => self::SERVICE_NAME,
            'checked_at' => $this->resource->toIso8601String(),
        ];
    }
}
