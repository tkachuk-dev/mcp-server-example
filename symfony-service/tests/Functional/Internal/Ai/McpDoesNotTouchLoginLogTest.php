<?php

declare(strict_types=1);

namespace App\Tests\Functional\Internal\Ai;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Repository\LoginLogRepository;
use App\Repository\TenantRepository;
use App\Repository\UserRepository;
use App\Tests\Support\InternalAiWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class McpDoesNotTouchLoginLogTest extends InternalAiWebTestCase
{
    private const string USER_EMAIL = 'user1@acme.test';

    public function testMcpRequestsDoNotWriteLoginJournalOrLastLogin(): void
    {
        $logsBefore = $this->loginLogCount();
        $lastLoginBefore = $this->user()->getLastLoginAt();

        foreach (['users-summary', 'login-activity', 'security-events'] as $endpoint) {
            self::assertSame(200, $this->mcpGet(self::BASE_PATH.'/1/'.$endpoint)->getStatusCode());
        }

        self::assertSame($logsBefore, $this->loginLogCount());
        self::assertEquals($lastLoginBefore, $this->user()->getLastLoginAt());
    }

    public function testRealUserLoginIsJournaled(): void
    {
        $logsBefore = $this->loginLogCount();
        $lastLoginBefore = $this->user()->getLastLoginAt();

        $this->client->jsonRequest('POST', '/api/auth/login', ['username' => self::USER_EMAIL, 'password' => AppFixtures::DEFAULT_PASSWORD]);

        self::assertSame(204, $this->client->getResponse()->getStatusCode());
        self::assertSame($logsBefore + 1, $this->loginLogCount());
        self::assertNotEquals($lastLoginBefore, $this->user()->getLastLoginAt());
    }

    private function loginLogCount(): int
    {
        $tenant = static::getContainer()->get(TenantRepository::class)->find(self::TENANT_ID);
        self::assertNotNull($tenant);

        return static::getContainer()->get(LoginLogRepository::class)->countForTenant($tenant);
    }

    private function user(): User
    {
        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $user = static::getContainer()->get(UserRepository::class)->findOneByEmail(self::USER_EMAIL);
        self::assertNotNull($user);

        return $user;
    }
}
