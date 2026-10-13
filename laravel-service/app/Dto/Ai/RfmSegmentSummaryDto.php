<?php

declare(strict_types=1);

namespace App\Dto\Ai;

use App\Enums\RfmSegment;

final readonly class RfmSegmentSummaryDto
{
    public function __construct(
        public RfmSegment $segment,
        public int $customersCount,
        public float $avgRecencyDays,
        public float $avgFrequency,
        public int $avgMonetaryMinor,
    ) {}
}
