<?php

declare(strict_types=1);

namespace App\Dto\Ai;

use Carbon\CarbonImmutable;

final readonly class CustomerMetricsDto
{
    public function __construct(
        public int $userId,
        public int $frequency,
        public int $monetaryMinor,
        public ?CarbonImmutable $lastOrderAt,
    ) {}
}
