<?php

declare(strict_types=1);

namespace App\Dto\Ai\Request;

use Symfony\Component\Validator\Constraints as Assert;

class SecurityEventsQuery
{
    public const int MIN_LIMIT = 1;
    public const int MAX_LIMIT = 200;
    public const int DEFAULT_LIMIT = 50;

    #[Assert\NotNull]
    #[Assert\Range(min: self::MIN_LIMIT, max: self::MAX_LIMIT)]
    private ?int $limit = self::DEFAULT_LIMIT;

    public function setLimit(?int $limit): void
    {
        $this->limit = $limit;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }
}
