<?php

declare(strict_types=1);

namespace App\Http\Resources\Ai;

use App\Dto\Ai\RevenueDayDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property RevenueDayDto $resource
 */
class RevenueDayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->resource->date->toDateString(),
            'revenue_minor' => $this->resource->revenueMinor,
            'orders_count' => $this->resource->ordersCount,
            'avg_order_value_minor' => $this->resource->avgOrderValueMinor,
            'refunds_minor' => $this->resource->refundsMinor,
            'refunds_count' => $this->resource->refundsCount,
            'new_subscriptions' => $this->resource->newSubscriptions,
            'cancelled_subscriptions' => $this->resource->cancelledSubscriptions,
        ];
    }
}
