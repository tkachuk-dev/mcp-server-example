<?php

declare(strict_types=1);

namespace App\Dto\Ai\Response;

use Symfony\Component\Serializer\Attribute\SerializedName;

class UsersSummaryResponse
{
    private ?int $tenantId = null;
    private ?\DateTimeImmutable $generatedAt = null;
    private ?int $totalUsers = null;

    // CamelCaseToSnakeCase would produce "active_last30_days".
    #[SerializedName('active_last_30_days')]
    private ?int $activeLast30Days = null;

    private ?int $blockedUsers = null;
    private ?int $unverifiedEmailUsers = null;
    private ?float $twoFactorEnabledRatio = null;

    /** @var list<RoleUsersCount>|null */
    private ?array $roles = null;

    public function setTenantId(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getTenantId(): int
    {
        return $this->tenantId;
    }

    public function setGeneratedAt(?\DateTimeImmutable $generatedAt): void
    {
        $this->generatedAt = $generatedAt;
    }

    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function setTotalUsers(?int $totalUsers): void
    {
        $this->totalUsers = $totalUsers;
    }

    public function getTotalUsers(): int
    {
        return $this->totalUsers;
    }

    public function setActiveLast30Days(?int $activeLast30Days): void
    {
        $this->activeLast30Days = $activeLast30Days;
    }

    public function getActiveLast30Days(): int
    {
        return $this->activeLast30Days;
    }

    public function setBlockedUsers(?int $blockedUsers): void
    {
        $this->blockedUsers = $blockedUsers;
    }

    public function getBlockedUsers(): int
    {
        return $this->blockedUsers;
    }

    public function setUnverifiedEmailUsers(?int $unverifiedEmailUsers): void
    {
        $this->unverifiedEmailUsers = $unverifiedEmailUsers;
    }

    public function getUnverifiedEmailUsers(): int
    {
        return $this->unverifiedEmailUsers;
    }

    public function setTwoFactorEnabledRatio(?float $twoFactorEnabledRatio): void
    {
        $this->twoFactorEnabledRatio = $twoFactorEnabledRatio;
    }

    public function getTwoFactorEnabledRatio(): float
    {
        return $this->twoFactorEnabledRatio;
    }

    /**
     * @param list<RoleUsersCount>|null $roles
     */
    public function setRoles(?array $roles): void
    {
        $this->roles = $roles;
    }

    /**
     * @return list<RoleUsersCount>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }
}
