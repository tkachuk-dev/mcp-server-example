<?php

declare(strict_types=1);

namespace App\Dto\Ai;

final readonly class GetChurnRiskDto
{
    public function __construct(
        public int $tenantId,
        public int $limit,
    ) {}
}
