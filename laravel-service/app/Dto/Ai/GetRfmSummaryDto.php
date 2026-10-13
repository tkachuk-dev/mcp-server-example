<?php

declare(strict_types=1);

namespace App\Dto\Ai;

final readonly class GetRfmSummaryDto
{
    public function __construct(
        public int $tenantId,
        public string $currency,
    ) {}
}
