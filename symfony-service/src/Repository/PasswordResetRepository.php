<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PasswordReset;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordReset>
 */
class PasswordResetRepository extends ServiceEntityRepository
{
    use DailyCountsTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordReset::class);
    }

    /**
     * @return array<string, int> Y-m-d => count
     */
    public function countDaily(Tenant $tenant, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<array{day: mixed, cnt: int|string}> $rows */
        $rows = $this->createQueryBuilder('p')
            ->select('p.createdOn AS day', 'COUNT(p.id) AS cnt')
            ->where('p.tenant = :tenant')
            ->andWhere('p.createdOn BETWEEN :from AND :to')
            ->groupBy('p.createdOn')
            ->setParameter('tenant', $tenant)
            ->setParameter('from', $from, Types::DATE_IMMUTABLE)
            ->setParameter('to', $to, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[$this->dayKey($row['day'])] = (int) $row['cnt'];
        }

        return $map;
    }
}
