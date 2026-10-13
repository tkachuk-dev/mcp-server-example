<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Dto\Ai\CustomerMetricsDto;
use App\Models\Order;
use App\Models\UserProjection;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerMetricsRepository
{
    private const string FREQUENCY = 'frequency';

    private const string MONETARY = 'monetary_minor';

    private const string LAST_ORDER_AT = 'last_order_at';

    /**
     * Customers of the tenant with at least one paid order, regardless of their status.
     *
     * @return list<CustomerMetricsDto>
     */
    public function forRfm(int $tenantId): array
    {
        $query = $this->withPaidOrderMetrics($tenantId)->whereHas('orders', $this->onlyPaid(...));

        return $this->toDtos($query);
    }

    /**
     * Active customers of the tenant (blocked/deleted ones excluded via the local projection),
     * including customers without any paid order.
     *
     * @return list<CustomerMetricsDto>
     */
    public function forChurn(int $tenantId): array
    {
        return $this->toDtos($this->withPaidOrderMetrics($tenantId)->active());
    }

    /**
     * @return Builder<UserProjection>
     */
    private function withPaidOrderMetrics(int $tenantId): Builder
    {
        return UserProjection::query()
            ->where('tenant_id', $tenantId)
            ->withCount(['orders as '.self::FREQUENCY => $this->onlyPaid(...)])
            ->withSum(['orders as '.self::MONETARY => $this->onlyPaid(...)], 'amount_minor')
            ->withMax(['orders as '.self::LAST_ORDER_AT => $this->onlyPaid(...)], 'paid_at');
    }

    /**
     * @param  Builder<Order>|HasMany<Order, UserProjection>  $query
     */
    private function onlyPaid(Builder|HasMany $query): void
    {
        $query->paid();
    }

    /**
     * @param  Builder<UserProjection>  $query
     * @return list<CustomerMetricsDto>
     */
    private function toDtos(Builder $query): array
    {
        return array_values($query->get()
            ->map(fn (UserProjection $customer): CustomerMetricsDto => $this->toDto($customer))
            ->all());
    }

    private function toDto(UserProjection $customer): CustomerMetricsDto
    {
        $lastOrderAt = $customer->getAttribute(self::LAST_ORDER_AT);

        return new CustomerMetricsDto(
            userId: $customer->user_id,
            frequency: (int) $customer->getAttribute(self::FREQUENCY),
            monetaryMinor: (int) $customer->getAttribute(self::MONETARY),
            lastOrderAt: $lastOrderAt === null ? null : CarbonImmutable::parse((string) $lastOrderAt, 'UTC'),
        );
    }
}
