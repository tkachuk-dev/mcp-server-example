<?php

declare(strict_types=1);

namespace App\Tests\Integration\Mcp;

use App\Mcp\Tool\BillingTools;
use App\Mcp\Tool\UsersTools;
use App\Tests\Support\Fixtures;
use App\Tests\Support\ToolTestCase;
use Mcp\Exception\ToolCallException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RetryAndPartialFailureTest extends ToolTestCase
{
    /**
     * @return iterable<string, array{int}>
     */
    public static function retryableStatuses(): iterable
    {
        yield '502' => [502];
        yield '503' => [503];
        yield '504' => [504];
    }

    #[DataProvider('retryableStatuses')]
    public function testRetriesOnceOnGatewayErrorsAndSucceeds(int $status): void
    {
        $this->responder()->queue(Fixtures::status($status), Fixtures::response(Fixtures::USERS, 'users-summary'));

        $result = $this->service(UsersTools::class)->getSummary(1);

        self::assertSame(Fixtures::decode(Fixtures::USERS, 'users-summary'), $result);
        self::assertCount(2, $this->responder()->backendRequests());
    }

    public function testGivesUpAfterOneRetry(): void
    {
        $this->responder()->queue(Fixtures::status(503), Fixtures::status(503));

        $this->assertToolError('Сервіс users тимчасово недоступний', fn () => $this->service(UsersTools::class)->getSummary(1));
        self::assertCount(2, $this->responder()->backendRequests());
    }

    public function testRetriesOnceOnNetworkError(): void
    {
        $this->responder()->queue(Fixtures::networkError(), Fixtures::response(Fixtures::BILLING, 'churn-risk'));

        $result = $this->service(BillingTools::class)->getChurnRisk(1);

        self::assertSame(Fixtures::decode(Fixtures::BILLING, 'churn-risk'), $result);
        self::assertCount(2, $this->responder()->backendRequests());
    }

    /**
     * @return iterable<string, array{MockResponse}>
     */
    public static function nonRetryable(): iterable
    {
        yield '400' => [Fixtures::status(400)];
        yield '401' => [Fixtures::status(401)];
        yield '403' => [Fixtures::status(403)];
        yield '404' => [Fixtures::response(Fixtures::USERS, 'error-404', 404)];
        yield '422' => [Fixtures::response(Fixtures::USERS, 'error-422', 422)];
        yield '429' => [Fixtures::status(429)];
        yield '500' => [Fixtures::status(500)];
    }

    #[DataProvider('nonRetryable')]
    public function testDoesNotRetryClientErrors(MockResponse $response): void
    {
        $this->responder()->queue($response);

        try {
            $this->service(UsersTools::class)->getLoginActivity(1);
            self::fail('Expected ToolCallException');
        } catch (ToolCallException) {
        }

        self::assertCount(1, $this->responder()->backendRequests());
    }

    public function testUsersToolsWorkWhileBillingIsDown(): void
    {
        $this->responder()->queue(
            Fixtures::networkError(),
            Fixtures::networkError(),
            Fixtures::response(Fixtures::USERS, 'security-events'),
        );

        $this->assertToolError('Сервіс billing тимчасово недоступний', fn () => $this->service(BillingTools::class)->getRevenueSummary(1));
        $result = $this->service(UsersTools::class)->getSecurityEvents(1);

        self::assertSame(Fixtures::decode(Fixtures::USERS, 'security-events'), $result);
    }

    public function testBillingToolsWorkWhileUsersIsDown(): void
    {
        $this->responder()->queue(
            Fixtures::status(503),
            Fixtures::status(503),
            Fixtures::response(Fixtures::BILLING, 'rfm-summary'),
        );

        $this->assertToolError('Сервіс users тимчасово недоступний', fn () => $this->service(UsersTools::class)->getSummary(1));
        $result = $this->service(BillingTools::class)->getRfmSummary(1);

        self::assertSame(Fixtures::decode(Fixtures::BILLING, 'rfm-summary'), $result);
    }

    private function assertToolError(string $message, \Closure $call): void
    {
        try {
            $call();
            self::fail('Expected ToolCallException');
        } catch (ToolCallException $e) {
            self::assertSame($message, $e->getMessage());
        }
    }
}
