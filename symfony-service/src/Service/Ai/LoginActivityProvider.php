<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Dto\Ai\Request\LoginActivityQuery;
use App\Dto\Ai\Response\DailyLoginActivity;
use App\Dto\Ai\Response\LoginActivityResponse;
use App\Entity\Tenant;
use App\Enums\LoginResult;
use App\Repository\LoginLogRepository;
use App\Repository\PasswordResetRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class LoginActivityProvider
{
    public const int CACHE_TTL_SECONDS = 600;
    private const string DAY_FORMAT = 'Y-m-d';

    public function __construct(
        private readonly TenantLookup $tenantLookup,
        private readonly LoginLogRepository $loginLogs,
        private readonly PasswordResetRepository $passwordResets,
        private readonly CacheInterface $cache,
        private readonly ClockInterface $clock,
    ) {
    }

    public function getActivity(int $tenantId, LoginActivityQuery $query): LoginActivityResponse
    {
        $tenant = $this->tenantLookup->getOrFail($tenantId);
        $days = $query->getDays();
        $today = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->setTime(0, 0);

        return $this->cache->get(
            sprintf('ai.login_activity.%d.%d.%s', $tenantId, $days, $today->format(self::DAY_FORMAT)),
            function (ItemInterface $item) use ($tenant, $tenantId, $days, $today): LoginActivityResponse {
                $item->expiresAfter(self::CACHE_TTL_SECONDS);

                return $this->build($tenant, $tenantId, $days, $today);
            },
        );
    }

    private function build(Tenant $tenant, int $tenantId, int $days, \DateTimeImmutable $today): LoginActivityResponse
    {
        $from = $today->modify(sprintf('-%d days', $days - 1));

        $response = new LoginActivityResponse();
        $response->setTenantId($tenantId);
        $response->setDays($days);
        $response->setPeriodStart($from);
        $response->setPeriodEnd($today);
        $response->setGeneratedAt($this->clock->now());
        $response->setDaily($this->daily($tenant, $from, $today));

        return $response;
    }

    /**
     * Every day of the period is present, days without activity are zero-filled.
     *
     * @return list<DailyLoginActivity>
     */
    private function daily(Tenant $tenant, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $successful = $this->loginLogs->countDailyByResult($tenant, LoginResult::Success, $from, $to);
        $failed = $this->loginLogs->countDailyByResult($tenant, LoginResult::Failure, $from, $to);
        $unique = $this->loginLogs->countDailyUniqueUsers($tenant, $from, $to);
        $resets = $this->passwordResets->countDaily($tenant, $from, $to);

        $daily = [];
        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            $key = $day->format(self::DAY_FORMAT);
            $item = new DailyLoginActivity();
            $item->setDate($day);
            $item->setSuccessfulLogins($successful[$key] ?? 0);
            $item->setFailedLogins($failed[$key] ?? 0);
            $item->setUniqueActiveUsers($unique[$key] ?? 0);
            $item->setPasswordResets($resets[$key] ?? 0);
            $daily[] = $item;
        }

        return $daily;
    }
}
