<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Order;
use App\Models\Refund;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Daily aggregates keyed by date (Y-m-d). Each entry: ['amount' => int, 'count' => int].
 */
class RevenueRepository
{
    /**
     * @return array<string, array{amount: int, count: int}>
     */
    public function dailyPaidOrders(int $tenantId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->groupByDay(Order::query()->paid()->where('tenant_id', $tenantId), 'paid_at', $from, $to);
    }

    /**
     * @return array<string, array{amount: int, count: int}>
     */
    public function dailyRefunds(int $tenantId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->groupByDay(Refund::query()->where('tenant_id', $tenantId), 'refunded_at', $from, $to);
    }

    /**
     * @return array<string, array{amount: int, count: int}>
     */
    public function dailyNewSubscriptions(int $tenantId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->groupByDay(Subscription::query()->where('tenant_id', $tenantId), 'started_at', $from, $to);
    }

    /**
     * @return array<string, array{amount: int, count: int}>
     */
    public function dailyCancelledSubscriptions(int $tenantId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->groupByDay(Subscription::query()->where('tenant_id', $tenantId), 'cancelled_at', $from, $to);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return array<string, array{amount: int, count: int}>
     */
    private function groupByDay(Builder $query, string $column, CarbonImmutable $from, CarbonImmutable $to): array
    {
        // The query builder cannot group by an expression, so the day is extracted with selectRaw.
        // DATE() behaves the same on PostgreSQL, MySQL and SQLite; $column is a constant from this class.
        $rows = $query
            ->whereBetween($column, [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw("DATE({$column}) as day")
            ->selectRaw('COUNT(*) as items_count')
            ->selectRaw('COALESCE(SUM(amount_minor), 0) as amount_sum')
            ->groupBy('day')
            ->toBase()
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $result[(string) $row->day] = ['amount' => (int) $row->amount_sum, 'count' => (int) $row->items_count];
        }

        return $result;
    }
}
