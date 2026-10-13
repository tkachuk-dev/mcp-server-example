<?php

declare(strict_types=1);

namespace App\Dto\Ai;

use Carbon\CarbonImmutable;

final readonly class RevenueDayDto
{
    public function __construct(
        public CarbonImmutable $date,
        public int $revenueMinor,
        public int $ordersCount,
        public int $avgOrderValueMinor,
        public int $refundsMinor,
        public int $refundsCount,
        public int $newSubscriptions,
        public int $cancelledSubscriptions,
    ) {}
}
