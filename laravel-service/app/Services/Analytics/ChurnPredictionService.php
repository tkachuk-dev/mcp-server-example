<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Dto\Ai\ChurnCustomerDto;
use App\Dto\Ai\ChurnRiskDto;
use App\Dto\Ai\CustomerMetricsDto;
use App\Dto\Ai\GetChurnRiskDto;
use App\Enums\RfmSegment;
use App\Repositories\CustomerMetricsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class ChurnPredictionService
{
    public const int RECENCY_HORIZON_DAYS = 180;

    public const int FREQUENCY_CAP = 10;

    public const float RECENCY_WEIGHT = 0.7;

    public const float FREQUENCY_WEIGHT = 0.3;

    public const float MAX_SCORE = 1.0;

    private const int SCORE_PRECISION = 2;

    private const string CACHE_KEY = 'internal_ai:v1:churn:%d:%d';

    public function __construct(
        private readonly CustomerMetricsRepository $customers,
        private readonly RfmSegmentationService $rfm,
    ) {}

    public function getChurnRisk(GetChurnRiskDto $dto): ChurnRiskDto
    {
        return Cache::remember(
            sprintf(self::CACHE_KEY, $dto->tenantId, $dto->limit),
            (int) config('internal_ai.cache_ttl_seconds'),
            fn (): ChurnRiskDto => $this->buildChurnRisk($dto),
        );
    }

    public function scoreFor(int $recencyDays, int $frequency): float
    {
        $recencyPart = min(self::MAX_SCORE, $recencyDays / self::RECENCY_HORIZON_DAYS);
        $frequencyPart = self::MAX_SCORE - min($frequency, self::FREQUENCY_CAP) / self::FREQUENCY_CAP;

        return round(self::RECENCY_WEIGHT * $recencyPart + self::FREQUENCY_WEIGHT * $frequencyPart, self::SCORE_PRECISION);
    }

    private function buildChurnRisk(GetChurnRiskDto $dto): ChurnRiskDto
    {
        $now = CarbonImmutable::now();

        $customers = array_map(
            fn (CustomerMetricsDto $customer): ChurnCustomerDto => $this->score($customer, $now),
            $this->customers->forChurn($dto->tenantId),
        );

        usort($customers, fn (ChurnCustomerDto $a, ChurnCustomerDto $b): int => [$b->churnScore, $a->userId] <=> [$a->churnScore, $b->userId]);

        return new ChurnRiskDto($dto->tenantId, $dto->limit, $now, array_slice($customers, 0, $dto->limit));
    }

    private function score(CustomerMetricsDto $customer, CarbonImmutable $now): ChurnCustomerDto
    {
        if ($customer->lastOrderAt === null) {
            return new ChurnCustomerDto($customer->userId, self::MAX_SCORE, RfmSegment::Lost, null);
        }

        $recency = $this->rfm->recencyDays($customer->lastOrderAt, $now);

        return new ChurnCustomerDto(
            userId: $customer->userId,
            churnScore: $this->scoreFor($recency, $customer->frequency),
            segment: $this->rfm->segmentFor($recency, $customer->frequency),
            lastOrderAt: $customer->lastOrderAt,
        );
    }
}
