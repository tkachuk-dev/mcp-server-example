<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SecurityEvent;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SecurityEvent>
 */
class SecurityEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecurityEvent::class);
    }

    /**
     * @return list<SecurityEvent>
     */
    public function findLatestForTenant(Tenant $tenant, int $limit): array
    {
        return $this->findBy(['tenant' => $tenant], ['occurredAt' => 'DESC', 'id' => 'DESC'], $limit);
    }
}
