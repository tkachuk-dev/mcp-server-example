<?php

declare(strict_types=1);

namespace App\Dto\Ai\Response;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

class LoginActivityResponse
{
    private ?int $tenantId = null;
    private ?int $days = null;

    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private ?\DateTimeImmutable $periodStart = null;

    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private ?\DateTimeImmutable $periodEnd = null;

    private ?\DateTimeImmutable $generatedAt = null;

    /** @var list<DailyLoginActivity>|null */
    private ?array $daily = null;

    public function setTenantId(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getTenantId(): int
    {
        return $this->tenantId;
    }

    public function setDays(?int $days): void
    {
        $this->days = $days;
    }

    public function getDays(): int
    {
        return $this->days;
    }

    public function setPeriodStart(?\DateTimeImmutable $periodStart): void
    {
        $this->periodStart = $periodStart;
    }

    public function getPeriodStart(): \DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function setPeriodEnd(?\DateTimeImmutable $periodEnd): void
    {
        $this->periodEnd = $periodEnd;
    }

    public function getPeriodEnd(): \DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function setGeneratedAt(?\DateTimeImmutable $generatedAt): void
    {
        $this->generatedAt = $generatedAt;
    }

    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }

    /**
     * @param list<DailyLoginActivity>|null $daily
     */
    public function setDaily(?array $daily): void
    {
        $this->daily = $daily;
    }

    /**
     * @return list<DailyLoginActivity>
     */
    public function getDaily(): array
    {
        return $this->daily;
    }
}
