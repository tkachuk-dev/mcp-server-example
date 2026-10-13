<?php

declare(strict_types=1);

namespace App\Dto\Ai;

use Carbon\CarbonImmutable;

final readonly class RevenueSummaryDto
{
    /**
     * @param  list<RevenueDayDto>  $daily
     */
    public function __construct(
        public int $tenantId,
        public int $days,
        public string $currency,
        public CarbonImmutable $periodStart,
        public CarbonImmutable $periodEnd,
        public CarbonImmutable $generatedAt,
        public array $daily,
    ) {}
}
