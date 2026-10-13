<?php

declare(strict_types=1);

namespace App\Dto\Ai;

final readonly class GetRevenueSummaryDto
{
    public function __construct(
        public int $tenantId,
        public string $currency,
        public int $days,
    ) {}
}
