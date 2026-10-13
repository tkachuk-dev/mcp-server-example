<?php

declare(strict_types=1);

namespace App\Repository;

trait DailyCountsTrait
{
    private function dayKey(mixed $day): string
    {
        if ($day instanceof \DateTimeInterface) {
            return $day->format('Y-m-d');
        }

        return substr((string) $day, 0, 10);
    }
}
