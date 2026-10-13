<?php

declare(strict_types=1);

namespace App\Dto\Ai\Response;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

class DailyLoginActivity
{
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private ?\DateTimeImmutable $date = null;

    private ?int $successfulLogins = null;
    private ?int $failedLogins = null;
    private ?int $uniqueActiveUsers = null;
    private ?int $passwordResets = null;

    public function setDate(?\DateTimeImmutable $date): void
    {
        $this->date = $date;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setSuccessfulLogins(?int $successfulLogins): void
    {
        $this->successfulLogins = $successfulLogins;
    }

    public function getSuccessfulLogins(): int
    {
        return $this->successfulLogins;
    }

    public function setFailedLogins(?int $failedLogins): void
    {
        $this->failedLogins = $failedLogins;
    }

    public function getFailedLogins(): int
    {
        return $this->failedLogins;
    }

    public function setUniqueActiveUsers(?int $uniqueActiveUsers): void
    {
        $this->uniqueActiveUsers = $uniqueActiveUsers;
    }

    public function getUniqueActiveUsers(): int
    {
        return $this->uniqueActiveUsers;
    }

    public function setPasswordResets(?int $passwordResets): void
    {
        $this->passwordResets = $passwordResets;
    }

    public function getPasswordResets(): int
    {
        return $this->passwordResets;
    }
}
