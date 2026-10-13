<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Dto\Ai\Request\SecurityEventsQuery;
use App\Dto\Ai\Response\SecurityEventItem;
use App\Dto\Ai\Response\SecurityEventsResponse;
use App\Entity\Tenant;
use App\Repository\SecurityEventRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class SecurityEventsProvider
{
    // Security events must stay fresh: never cached longer than a minute.
    public const int CACHE_TTL_SECONDS = 60;

    public function __construct(
        private readonly TenantLookup $tenantLookup,
        private readonly SecurityEventRepository $securityEvents,
        private readonly CacheInterface $cache,
        private readonly ClockInterface $clock,
    ) {
    }

    public function getEvents(int $tenantId, SecurityEventsQuery $query): SecurityEventsResponse
    {
        $tenant = $this->tenantLookup->getOrFail($tenantId);
        $limit = $query->getLimit();

        return $this->cache->get(
            sprintf('ai.security_events.%d.%d', $tenantId, $limit),
            function (ItemInterface $item) use ($tenant, $tenantId, $limit): SecurityEventsResponse {
                $item->expiresAfter(self::CACHE_TTL_SECONDS);

                return $this->build($tenant, $tenantId, $limit);
            },
        );
    }

    private function build(Tenant $tenant, int $tenantId, int $limit): SecurityEventsResponse
    {
        $response = new SecurityEventsResponse();
        $response->setTenantId($tenantId);
        $response->setLimit($limit);
        $response->setGeneratedAt($this->clock->now());
        $response->setEvents(array_map(
            SecurityEventItem::fromEntity(...),
            $this->securityEvents->findLatestForTenant($tenant, $limit),
        ));

        return $response;
    }
}
