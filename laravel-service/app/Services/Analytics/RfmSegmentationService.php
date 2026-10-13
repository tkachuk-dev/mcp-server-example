<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Dto\Ai\CustomerMetricsDto;
use App\Dto\Ai\GetRfmSummaryDto;
use App\Dto\Ai\RfmSegmentSummaryDto;
use App\Dto\Ai\RfmSummaryDto;
use App\Enums\RfmSegment;
use App\Repositories\CustomerMetricsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class RfmSegmentationService
{
    public const int CHAMPIONS_MAX_RECENCY_DAYS = 30;

    public const int CHAMPIONS_MIN_FREQUENCY = 5;

    public const int LOYAL_MAX_RECENCY_DAYS = 60;

    public const int LOYAL_MIN_FREQUENCY = 3;

    public const int POTENTIAL_MAX_RECENCY_DAYS = 60;

    public const int AT_RISK_MAX_RECENCY_DAYS = 120;

    public const int HIBERNATING_MAX_RECENCY_DAYS = 240;

    private const string CACHE_KEY = 'internal_ai:v1:rfm:%d';

    private const int AVERAGE_PRECISION = 1;

    public function __construct(private readonly CustomerMetricsRepository $customers) {}

    public function getSummary(GetRfmSummaryDto $dto): RfmSummaryDto
    {
        return Cache::remember(
            sprintf(self::CACHE_KEY, $dto->tenantId),
            (int) config('internal_ai.cache_ttl_seconds'),
            fn (): RfmSummaryDto => $this->buildSummary($dto),
        );
    }

    public function segmentFor(int $recencyDays, int $frequency): RfmSegment
    {
        return match (true) {
            $recencyDays <= self::CHAMPIONS_MAX_RECENCY_DAYS && $frequency >= self::CHAMPIONS_MIN_FREQUENCY => RfmSegment::Champions,
            $recencyDays <= self::LOYAL_MAX_RECENCY_DAYS && $frequency >= self::LOYAL_MIN_FREQUENCY => RfmSegment::Loyal,
            $recencyDays <= self::POTENTIAL_MAX_RECENCY_DAYS => RfmSegment::Potential,
            $recencyDays <= self::AT_RISK_MAX_RECENCY_DAYS => RfmSegment::AtRisk,
            $recencyDays <= self::HIBERNATING_MAX_RECENCY_DAYS => RfmSegment::Hibernating,
            default => RfmSegment::Lost,
        };
    }

    public function recencyDays(CarbonImmutable $lastOrderAt, CarbonImmutable $now): int
    {
        return max(0, (int) $lastOrderAt->startOfDay()->diffInDays($now->startOfDay()));
    }

    private function buildSummary(GetRfmSummaryDto $dto): RfmSummaryDto
    {
        $now = CarbonImmutable::now();
        $grouped = $this->groupBySegment($this->customers->forRfm($dto->tenantId), $now);

        $segments = array_map(
            fn (RfmSegment $segment): RfmSegmentSummaryDto => $this->summarize($segment, $grouped[$segment->value] ?? []),
            RfmSegment::cases(),
        );

        return new RfmSummaryDto($dto->tenantId, $dto->currency, $now, $segments);
    }

    /**
     * @param  list<CustomerMetricsDto>  $customers
     * @return array<string, list<array{recency: int, customer: CustomerMetricsDto}>>
     */
    private function groupBySegment(array $customers, CarbonImmutable $now): array
    {
        $grouped = [];

        foreach ($customers as $customer) {
            if ($customer->lastOrderAt === null) {
                continue;
            }

            $recency = $this->recencyDays($customer->lastOrderAt, $now);
            $grouped[$this->segmentFor($recency, $customer->frequency)->value][] = ['recency' => $recency, 'customer' => $customer];
        }

        return $grouped;
    }

    /**
     * @param  list<array{recency: int, customer: CustomerMetricsDto}>  $members
     */
    private function summarize(RfmSegment $segment, array $members): RfmSegmentSummaryDto
    {
        $count = count($members);

        if ($count === 0) {
            return new RfmSegmentSummaryDto($segment, 0, 0.0, 0.0, 0);
        }

        $recency = array_sum(array_column($members, 'recency'));
        $frequency = array_sum(array_map(fn (array $m): int => $m['customer']->frequency, $members));
        $monetary = array_sum(array_map(fn (array $m): int => $m['customer']->monetaryMinor, $members));

        return new RfmSegmentSummaryDto(
            segment: $segment,
            customersCount: $count,
            avgRecencyDays: round($recency / $count, self::AVERAGE_PRECISION),
            avgFrequency: round($frequency / $count, self::AVERAGE_PRECISION),
            avgMonetaryMinor: intdiv($monetary, $count),
        );
    }
}
