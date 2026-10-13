<?php

declare(strict_types=1);

namespace App\Dto\Ai;

use App\Enums\RfmSegment;
use Carbon\CarbonImmutable;

final readonly class ChurnCustomerDto
{
    // lastOrderAt is nullable on purpose: the contract allows customers without orders.
    public function __construct(
        public int $userId,
        public float $churnScore,
        public RfmSegment $segment,
        public ?CarbonImmutable $lastOrderAt,
    ) {}
}
