<?php

declare(strict_types=1);

namespace App\Enums;

enum RfmSegment: string
{
    case Champions = 'champions';
    case Loyal = 'loyal';
    case Potential = 'potential';
    case AtRisk = 'at_risk';
    case Hibernating = 'hibernating';
    case Lost = 'lost';
}
