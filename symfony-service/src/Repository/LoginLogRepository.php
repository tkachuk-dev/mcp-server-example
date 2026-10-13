<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LoginLog;
use App\Entity\Tenant;
use App\Enums\LoginResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoginLog>
 */
class LoginLogRepository extends ServiceEntityRepository
{
    use DailyCountsTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginLog::class);
    }

    /**
     * @return array<string, int> Y-m-d => count
     */
    public function countDailyByResult(Tenant $tenant, LoginResult $result, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $qb = $this->dailyQuery($tenant, $from, $to)
            ->addSelect('COUNT(l.id) AS cnt')
            ->andWhere('l.result = :result')
            ->setParameter('result', $result->value);

        return $this->toDailyMap($qb);
    }

    /**
     * @return array<string, int> Y-m-d => distinct users with a successful login
     */
    public function countDailyUniqueUsers(Tenant $tenant, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $qb = $this->dailyQuery($tenant, $from, $to)
            ->addSelect('COUNT(DISTINCT IDENTITY(l.user)) AS cnt')
            ->andWhere('l.result = :result')
            ->setParameter('result', LoginResult::Success->value);

        return $this->toDailyMap($qb);
    }

    public function countForTenant(Tenant $tenant): int
    {
        return $this->count(['tenant' => $tenant]);
    }

    private function dailyQuery(Tenant $tenant, \DateTimeImmutable $from, \DateTimeImmutable $to): QueryBuilder
    {
        return $this->createQueryBuilder('l')
            ->select('l.createdOn AS day')
            ->where('l.tenant = :tenant')
            ->andWhere('l.createdOn BETWEEN :from AND :to')
            ->groupBy('l.createdOn')
            ->setParameter('tenant', $tenant)
            ->setParameter('from', $from, Types::DATE_IMMUTABLE)
            ->setParameter('to', $to, Types::DATE_IMMUTABLE);
    }

    /**
     * @return array<string, int>
     */
    private function toDailyMap(QueryBuilder $qb): array
    {
        /** @var list<array{day: mixed, cnt: int|string}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[$this->dayKey($row['day'])] = (int) $row['cnt'];
        }

        return $map;
    }
}
