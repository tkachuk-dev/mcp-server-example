<?php

declare(strict_types=1);

namespace App\Dto\Ai\Request;

use Symfony\Component\Validator\Constraints as Assert;

class LoginActivityQuery
{
    public const int MIN_DAYS = 1;
    public const int MAX_DAYS = 90;
    public const int DEFAULT_DAYS = 30;

    #[Assert\NotNull]
    #[Assert\Range(min: self::MIN_DAYS, max: self::MAX_DAYS)]
    private ?int $days = self::DEFAULT_DAYS;

    public function setDays(?int $days): void
    {
        $this->days = $days;
    }

    public function getDays(): int
    {
        return $this->days;
    }
}
