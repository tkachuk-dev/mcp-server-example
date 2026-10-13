<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

abstract class ToolTestCase extends KernelTestCase
{
    protected const string ACTING_USER = 'alice@example.com';
    protected const string USERS_TOKEN = 'users-test-token';
    protected const string BILLING_TOKEN = 'billing-test-token';
    protected const string TRACEPARENT_PATTERN = '/^00-[0-9a-f]{32}-[0-9a-f]{16}-01$/';

    protected function setUp(): void
    {
        self::bootKernel();
        $user = new InMemoryUser(self::ACTING_USER, null, ['ROLE_USER']);
        $this->service(TokenStorageInterface::class, 'security.token_storage')
            ->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }

    protected function responder(): HttpMockResponder
    {
        return $this->service(HttpMockResponder::class);
    }

    /**
     * @return list<LogRecord>
     */
    protected function auditRecords(): array
    {
        return array_values($this->service(TestHandler::class, 'app.test.audit_handler')->getRecords());
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected function service(string $class, ?string $id = null): object
    {
        $service = self::getContainer()->get($id ?? $class);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
