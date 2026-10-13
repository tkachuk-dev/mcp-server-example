<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Dto\Ai\Response\RoleUsersCount;
use App\Dto\Ai\Response\UsersSummaryResponse;
use App\Entity\Tenant;
use App\Enums\Role;
use App\Repository\UserRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class UsersSummaryProvider
{
    public const int CACHE_TTL_SECONDS = 600;
    public const int ACTIVE_WINDOW_DAYS = 30;
    private const int RATIO_PRECISION = 2;

    public function __construct(
        private readonly TenantLookup $tenantLookup,
        private readonly UserRepository $users,
        private readonly CacheInterface $cache,
        private readonly ClockInterface $clock,
    ) {
    }

    public function getSummary(int $tenantId): UsersSummaryResponse
    {
        $tenant = $this->tenantLookup->getOrFail($tenantId);

        return $this->cache->get(
            sprintf('ai.users_summary.%d', $tenantId),
            function (ItemInterface $item) use ($tenant, $tenantId): UsersSummaryResponse {
                $item->expiresAfter(self::CACHE_TTL_SECONDS);

                return $this->build($tenant, $tenantId);
            },
        );
    }

    private function build(Tenant $tenant, int $tenantId): UsersSummaryResponse
    {
        $now = $this->clock->now();
        $counts = $this->users->getSummaryCounts($tenant, $now->modify(sprintf('-%d days', self::ACTIVE_WINDOW_DAYS)));

        $summary = new UsersSummaryResponse();
        $summary->setTenantId($tenantId);
        $summary->setGeneratedAt($now);
        $summary->setTotalUsers($counts['total']);
        $summary->setActiveLast30Days($counts['active']);
        $summary->setBlockedUsers($counts['blocked']);
        $summary->setUnverifiedEmailUsers($counts['unverified']);
        $summary->setTwoFactorEnabledRatio($this->ratio($counts['two_factor'], $counts['total']));
        $summary->setRoles($this->roles($tenant));

        return $summary;
    }

    private function ratio(int $part, int $total): float
    {
        return 0 === $total ? 0.0 : round($part / $total, self::RATIO_PRECISION);
    }

    /**
     * @return list<RoleUsersCount>
     */
    private function roles(Tenant $tenant): array
    {
        $roles = [];
        foreach ($this->users->countByRole($tenant) as $role => $count) {
            $roles[] = RoleUsersCount::fromRole(Role::from($role), $count);
        }

        return $roles;
    }
}
