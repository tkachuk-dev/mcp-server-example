<?php

declare(strict_types=1);

namespace App\Service\Health;

use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class ReadinessChecker
{
    private const string PROBE_KEY = 'health.ready.probe';

    public function __construct(
        private readonly Connection $connection,
        private readonly CacheItemPoolInterface $cacheApp,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isReady(): bool
    {
        return $this->databaseIsReachable() && $this->cacheIsReachable();
    }

    private function databaseIsReachable(): bool
    {
        try {
            $this->connection->executeQuery($this->connection->getDatabasePlatform()->getDummySelectSQL());

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Readiness: database unavailable', ['exception' => $e]);

            return false;
        }
    }

    private function cacheIsReachable(): bool
    {
        try {
            $item = $this->cacheApp->getItem(self::PROBE_KEY)->set(true);

            return $this->cacheApp->save($item) && $this->cacheApp->deleteItem(self::PROBE_KEY);
        } catch (\Throwable $e) {
            $this->logger->error('Readiness: cache unavailable', ['exception' => $e]);

            return false;
        }
    }
}
