<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\LoginLog;
use App\Entity\PasswordReset;
use App\Entity\SecurityEvent;
use App\Entity\Tenant;
use App\Entity\User;
use App\Enums\LoginResult;
use App\Enums\Role;
use App\Enums\SecurityEventType;
use App\Enums\UserStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Deterministic data relative to "today" (UTC); functional tests assert these exact aggregates.
 *
 * Tenant 1: 10 live users (7 user / 2 manager / 1 admin), 1 blocked, 2 unverified, 4 with 2FA,
 * 6 logged in within 30 days, plus 1 deleted user that every aggregate must ignore.
 * Login log for 40 days: on day offset d there are (d % 3) + 1 successful logins by distinct users
 * and one failed login when d is even. Password resets on offsets 1, 2 and 5.
 */
class AppFixtures extends Fixture
{
    public const string DEFAULT_PASSWORD = 'password';
    public const int LOGIN_HISTORY_DAYS = 40;
    public const array PASSWORD_RESET_DAY_OFFSETS = [1, 2, 5];
    public const int TENANT_ONE_SECURITY_EVENTS = 8;

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ClockInterface $clock,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));

        $tenantOne = new Tenant('Acme', $now->modify('-1 year'));
        $tenantTwo = new Tenant('Globex', $now->modify('-1 year'));
        $manager->persist($tenantOne);
        $manager->persist($tenantTwo);

        $users = $this->createTenantOneUsers($manager, $tenantOne, $now);
        $this->createLoginHistory($manager, $tenantOne, $users, $now);
        $this->createPasswordResets($manager, $users[0], $now);
        $this->createSecurityEvents($manager, $tenantOne, $users, $now);
        $this->createTenantTwo($manager, $tenantTwo, $now);

        $manager->flush();
    }

    /**
     * @return list<User>
     */
    private function createTenantOneUsers(ObjectManager $manager, Tenant $tenant, \DateTimeImmutable $now): array
    {
        $roles = [Role::User, Role::User, Role::User, Role::User, Role::User, Role::User, Role::User, Role::Manager, Role::Manager, Role::Admin];
        $users = [];
        foreach ($roles as $i => $role) {
            $user = $this->createUser($tenant, sprintf('user%d@acme.test', $i + 1), $now);
            $user->setRole($role);
            $user->setEmailVerified($i < 8);
            $user->setTwoFactorEnabled($i >= 6);
            $user->setStatus(9 === $i ? UserStatus::Blocked : UserStatus::Active);
            $user->setLastLoginAt($i < 6 ? $now->modify(sprintf('-%d days', $i + 1)) : $now->modify('-60 days'));
            $manager->persist($user);
            $users[] = $user;
        }

        $deleted = $this->createUser($tenant, 'deleted@acme.test', $now);
        $deleted->setStatus(UserStatus::Deleted);
        $deleted->setTwoFactorEnabled(true);
        $deleted->setLastLoginAt($now->modify('-1 day'));
        $manager->persist($deleted);

        return $users;
    }

    /**
     * @param list<User> $users
     */
    private function createLoginHistory(ObjectManager $manager, Tenant $tenant, array $users, \DateTimeImmutable $now): void
    {
        for ($d = 0; $d < self::LOGIN_HISTORY_DAYS; ++$d) {
            $day = $now->setTime(10, 0)->modify(sprintf('-%d days', $d));
            for ($i = 0; $i <= $d % 3; ++$i) {
                $manager->persist(new LoginLog($tenant, $users[$i], LoginResult::Success, $day->modify(sprintf('+%d minutes', $i))));
            }
            if (0 === $d % 2) {
                $manager->persist(new LoginLog($tenant, $users[0], LoginResult::Failure, $day->modify('+30 minutes')));
            }
        }
    }

    private function createPasswordResets(ObjectManager $manager, User $user, \DateTimeImmutable $now): void
    {
        foreach (self::PASSWORD_RESET_DAY_OFFSETS as $offset) {
            $manager->persist(new PasswordReset($user, $now->setTime(9, 0)->modify(sprintf('-%d days', $offset))));
        }
    }

    /**
     * @param list<User> $users
     */
    private function createSecurityEvents(ObjectManager $manager, Tenant $tenant, array $users, \DateTimeImmutable $now): void
    {
        $events = [
            [SecurityEventType::FailedLoginBurst, null],
            [SecurityEventType::AccountLocked, $users[9]],
            [SecurityEventType::TwoFactorDisabled, $users[1]],
            [SecurityEventType::PasswordReset, $users[0]],
            [SecurityEventType::RoleChanged, $users[7]],
            [SecurityEventType::FailedLoginBurst, $users[0]],
            [SecurityEventType::PasswordReset, $users[2]],
            [SecurityEventType::AccountLocked, null],
        ];

        foreach ($events as $hoursAgo => [$type, $user]) {
            $manager->persist(new SecurityEvent($tenant, $user, $type, $now->modify(sprintf('-%d hours', $hoursAgo + 1))));
        }
    }

    private function createTenantTwo(ObjectManager $manager, Tenant $tenant, \DateTimeImmutable $now): void
    {
        $user = $this->createUser($tenant, 'user1@globex.test', $now);
        $user->setEmailVerified(true);
        $user->setLastLoginAt($now->modify('-2 days'));
        $manager->persist($user);

        $manager->persist(new LoginLog($tenant, $user, LoginResult::Success, $now->setTime(10, 0)));
        $manager->persist(new SecurityEvent($tenant, $user, SecurityEventType::RoleChanged, $now->modify('-30 minutes')));
    }

    private function createUser(Tenant $tenant, string $email, \DateTimeImmutable $now): User
    {
        $user = new User($tenant, $email, $now->modify('-200 days'));
        $user->setPassword($this->passwordHasher->hashPassword($user, self::DEFAULT_PASSWORD));

        return $user;
    }
}
