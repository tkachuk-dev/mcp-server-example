<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tenant;
use App\Entity\User;
use App\Enums\Role;
use App\Enums\UserStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Deleted users are excluded from every counter.
     *
     * @return array{total: int, active: int, blocked: int, unverified: int, two_factor: int}
     */
    public function getSummaryCounts(Tenant $tenant, \DateTimeImmutable $activeSince): array
    {
        /** @var array<string, int|string|null> $row */
        $row = $this->createQueryBuilder('u')
            ->select('COUNT(u.id) AS total')
            ->addSelect('SUM(CASE WHEN u.lastLoginAt >= :activeSince THEN 1 ELSE 0 END) AS active')
            ->addSelect('SUM(CASE WHEN u.status = :blocked THEN 1 ELSE 0 END) AS blocked')
            ->addSelect('SUM(CASE WHEN u.emailVerified = false THEN 1 ELSE 0 END) AS unverified')
            ->addSelect('SUM(CASE WHEN u.twoFactorEnabled = true THEN 1 ELSE 0 END) AS two_factor')
            ->where('u.tenant = :tenant')
            ->andWhere('u.status != :deleted')
            ->setParameter('tenant', $tenant)
            ->setParameter('activeSince', $activeSince, Types::DATETIME_IMMUTABLE)
            ->setParameter('blocked', UserStatus::Blocked->value)
            ->setParameter('deleted', UserStatus::Deleted->value)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $row['total'],
            'active' => (int) $row['active'],
            'blocked' => (int) $row['blocked'],
            'unverified' => (int) $row['unverified'],
            'two_factor' => (int) $row['two_factor'],
        ];
    }

    /**
     * @return array<int, int> role value => users count, ordered by role
     */
    public function countByRole(Tenant $tenant): array
    {
        /** @var list<array{role: Role|int|string, users_count: int|string}> $rows */
        $rows = $this->createQueryBuilder('u')
            ->select('u.role AS role', 'COUNT(u.id) AS users_count')
            ->where('u.tenant = :tenant')
            ->andWhere('u.status != :deleted')
            ->groupBy('u.role')
            ->orderBy('u.role', 'ASC')
            ->setParameter('tenant', $tenant)
            ->setParameter('deleted', UserStatus::Deleted->value)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $role = $row['role'] instanceof Role ? $row['role']->value : (int) $row['role'];
            $counts[$role] = (int) $row['users_count'];
        }

        return $counts;
    }
}
