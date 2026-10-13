<?php

declare(strict_types=1);

namespace App\Dto\Ai;

use Carbon\CarbonImmutable;

final readonly class ChurnRiskDto
{
    /**
     * @param  list<ChurnCustomerDto>  $customers
     */
    public function __construct(
        public int $tenantId,
        public int $limit,
        public CarbonImmutable $generatedAt,
        public array $customers,
    ) {}
}
