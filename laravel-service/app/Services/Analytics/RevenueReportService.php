<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Dto\Ai\GetRevenueSummaryDto;
use App\Dto\Ai\RevenueDayDto;
use App\Dto\Ai\RevenueSummaryDto;
use App\Repositories\RevenueRepository;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriodImmutable;
use Illuminate\Support\Facades\Cache;

class RevenueReportService
{
    private const string CACHE_KEY = 'internal_ai:v1:revenue:%d:%d:%s';

    private const string DAY_FORMAT = 'Y-m-d';

    public function __construct(private readonly RevenueRepository $revenue) {}

    public function getRevenueSummary(GetRevenueSummaryDto $dto): RevenueSummaryDto
    {
        $now = CarbonImmutable::now();

        return Cache::remember(
            sprintf(self::CACHE_KEY, $dto->tenantId, $dto->days, $now->format(self::DAY_FORMAT)),
            (int) config('internal_ai.cache_ttl_seconds'),
            fn (): RevenueSummaryDto => $this->buildSummary($dto, $now),
        );
    }

    private function buildSummary(GetRevenueSummaryDto $dto, CarbonImmutable $now): RevenueSummaryDto
    {
        $periodEnd = $now->startOfDay();
        $periodStart = $periodEnd->subDays($dto->days - 1);

        $orders = $this->revenue->dailyPaidOrders($dto->tenantId, $periodStart, $periodEnd);
        $refunds = $this->revenue->dailyRefunds($dto->tenantId, $periodStart, $periodEnd);
        $started = $this->revenue->dailyNewSubscriptions($dto->tenantId, $periodStart, $periodEnd);
        $cancelled = $this->revenue->dailyCancelledSubscriptions($dto->tenantId, $periodStart, $periodEnd);

        $daily = [];

        foreach (CarbonPeriodImmutable::create($periodStart, $periodEnd) as $date) {
            $key = $date->format(self::DAY_FORMAT);
            $revenueMinor = $orders[$key]['amount'] ?? 0;
            $ordersCount = $orders[$key]['count'] ?? 0;

            $daily[] = new RevenueDayDto(
                date: $date,
                revenueMinor: $revenueMinor,
                ordersCount: $ordersCount,
                avgOrderValueMinor: $ordersCount > 0 ? intdiv($revenueMinor, $ordersCount) : 0,
                refundsMinor: $refunds[$key]['amount'] ?? 0,
                refundsCount: $refunds[$key]['count'] ?? 0,
                newSubscriptions: $started[$key]['count'] ?? 0,
                cancelledSubscriptions: $cancelled[$key]['count'] ?? 0,
            );
        }

        return new RevenueSummaryDto($dto->tenantId, $dto->days, $dto->currency, $periodStart, $periodEnd, $now, $daily);
    }
}
