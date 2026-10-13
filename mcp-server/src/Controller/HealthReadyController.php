<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Readiness checks only what this service owns (cache.app: sessions, rate limits, token cache).
 * Backends are deliberately not checked: one backend being down must not take the whole MCP server out.
 */
final readonly class HealthReadyController
{
    public const string UNAVAILABLE_MESSAGE = 'Service unavailable';

    private const string PROBE_KEY = 'health.ready.probe';

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/health/ready', name: 'health_ready', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        if (!$this->isCacheWritable()) {
            return new JsonResponse(['message' => self::UNAVAILABLE_MESSAGE], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse(['service' => HealthLiveController::SERVICE]);
    }

    private function isCacheWritable(): bool
    {
        try {
            $item = $this->cache->getItem(self::PROBE_KEY)->set(time());

            return $this->cache->save($item) && $this->cache->hasItem(self::PROBE_KEY);
        } catch (\Throwable $e) {
            $this->logger->error('Readiness check failed', ['exception' => $e::class]);

            return false;
        }
    }
}
