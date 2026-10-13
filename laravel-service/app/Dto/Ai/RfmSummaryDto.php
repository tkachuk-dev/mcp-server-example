<?php

declare(strict_types=1);

namespace App\Dto\Ai;

use Carbon\CarbonImmutable;

final readonly class RfmSummaryDto
{
    /**
     * @param  list<RfmSegmentSummaryDto>  $segments
     */
    public function __construct(
        public int $tenantId,
        public string $currency,
        public CarbonImmutable $generatedAt,
        public array $segments,
    ) {}
}
