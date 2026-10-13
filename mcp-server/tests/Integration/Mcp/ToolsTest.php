<?php

declare(strict_types=1);

namespace App\Tests\Integration\Mcp;

use App\Mcp\Tool\BillingTools;
use App\Mcp\Tool\UsersTools;
use App\Tests\Support\Fixtures;
use App\Tests\Support\HttpMockResponder;
use App\Tests\Support\ToolTestCase;
use Mcp\Exception\ToolCallException;
use PHPUnit\Framework\Attributes\DataProvider;

final class ToolsTest extends ToolTestCase
{
    /**
     * @return iterable<string, array{class-string, string, list<int>, string, string, string, string}>
     */
    public static function tools(): iterable
    {
        yield 'users_get_summary' => [UsersTools::class, 'getSummary', [1], 'http://users-service/internal/ai/v1/tenants/1/users-summary', self::USERS_TOKEN, Fixtures::USERS, 'users-summary'];
        yield 'users_get_login_activity' => [UsersTools::class, 'getLoginActivity', [1], 'http://users-service/internal/ai/v1/tenants/1/login-activity?days=30', self::USERS_TOKEN, Fixtures::USERS, 'login-activity'];
        yield 'users_get_security_events' => [UsersTools::class, 'getSecurityEvents', [1], 'http://users-service/internal/ai/v1/tenants/1/security-events?limit=50', self::USERS_TOKEN, Fixtures::USERS, 'security-events'];
        yield 'billing_get_rfm_summary' => [BillingTools::class, 'getRfmSummary', [1], 'http://billing-service/internal/ai/v1/tenants/1/rfm-summary', self::BILLING_TOKEN, Fixtures::BILLING, 'rfm-summary'];
        yield 'billing_get_churn_risk' => [BillingTools::class, 'getChurnRisk', [1], 'http://billing-service/internal/ai/v1/tenants/1/churn-risk?limit=50', self::BILLING_TOKEN, Fixtures::BILLING, 'churn-risk'];
        yield 'billing_get_revenue_summary' => [BillingTools::class, 'getRevenueSummary', [1], 'http://billing-service/internal/ai/v1/tenants/1/revenue-summary?days=30', self::BILLING_TOKEN, Fixtures::BILLING, 'revenue-summary'];
    }

    /**
     * @param class-string $class
     * @param list<int>    $args
     */
    #[DataProvider('tools')]
    public function testCallsBackendWithUrlHeadersAndReturnsPayload(string $class, string $method, array $args, string $url, string $token, string $fixtureService, string $fixture): void
    {
        $this->responder()->queue(Fixtures::response($fixtureService, $fixture));

        $result = $this->call($class, $method, $args);

        self::assertSame(Fixtures::decode($fixtureService, $fixture), $result);
        $requests = $this->responder()->backendRequests();
        self::assertCount(1, $requests);
        self::assertSame('GET', $requests[0]['method']);
        self::assertSame($url, $requests[0]['url']);
        self::assertSame('Bearer '.$token, HttpMockResponder::header($requests[0], 'Authorization'));
        self::assertSame(self::ACTING_USER, HttpMockResponder::header($requests[0], 'X-Acting-User'));
        self::assertMatchesRegularExpression(self::TRACEPARENT_PATTERN, (string) HttpMockResponder::header($requests[0], 'traceparent'));
        self::assertNotEmpty(HttpMockResponder::header($requests[0], 'X-Request-Id'));
    }

    /**
     * @param class-string $class
     * @param list<int>    $args
     */
    #[DataProvider('tools')]
    public function testBackend404IsTenantNotFound(string $class, string $method, array $args, string $url, string $token, string $fixtureService, string $fixture): void
    {
        $this->responder()->queue(Fixtures::response($fixtureService, 'error-404', 404));

        $this->expectToolError('Tenant не знайдено', fn () => $this->call($class, $method, $args));
        self::assertCount(1, $this->responder()->backendRequests());
    }

    /**
     * @param class-string $class
     * @param list<int>    $args
     */
    #[DataProvider('tools')]
    public function testBackend422NamesTheParameterOnly(string $class, string $method, array $args, string $url, string $token, string $fixtureService, string $fixture): void
    {
        $this->responder()->queue(Fixtures::response($fixtureService, 'error-422', 422));
        $parameter = Fixtures::USERS === $fixtureService ? 'days' : 'limit';

        $this->expectToolError('Невірні параметри: '.$parameter, fn () => $this->call($class, $method, $args));
        self::assertCount(1, $this->responder()->backendRequests());
    }

    /**
     * @param class-string $class
     * @param list<int>    $args
     */
    #[DataProvider('tools')]
    public function testBackend500IsGenericUnavailableWithoutDetails(string $class, string $method, array $args, string $url, string $token, string $fixtureService, string $fixture): void
    {
        $this->responder()->queue(Fixtures::status(500));
        $service = Fixtures::USERS === $fixtureService ? 'users' : 'billing';

        $exception = $this->expectToolError(\sprintf('Сервіс %s тимчасово недоступний', $service), fn () => $this->call($class, $method, $args));
        self::assertStringNotContainsString('http', $exception->getMessage());
        self::assertStringNotContainsString('internal details', $exception->getMessage());
        self::assertCount(1, $this->responder()->backendRequests());
    }

    /**
     * @return iterable<string, array{class-string, string, list<int>, string}>
     */
    public static function invalidArguments(): iterable
    {
        yield 'users summary tenantId=0' => [UsersTools::class, 'getSummary', [0], 'tenantId'];
        yield 'users login days=0' => [UsersTools::class, 'getLoginActivity', [1, 0], 'days'];
        yield 'users login days=91' => [UsersTools::class, 'getLoginActivity', [1, 91], 'days'];
        yield 'users events limit=0' => [UsersTools::class, 'getSecurityEvents', [1, 0], 'limit'];
        yield 'users events limit=201' => [UsersTools::class, 'getSecurityEvents', [1, 201], 'limit'];
        yield 'billing rfm tenantId=-1' => [BillingTools::class, 'getRfmSummary', [-1], 'tenantId'];
        yield 'billing churn limit=0' => [BillingTools::class, 'getChurnRisk', [1, 0], 'limit'];
        yield 'billing churn limit=1000' => [BillingTools::class, 'getChurnRisk', [1, 1000], 'limit'];
        yield 'billing revenue days=0' => [BillingTools::class, 'getRevenueSummary', [1, 0], 'days'];
        yield 'billing revenue days=366' => [BillingTools::class, 'getRevenueSummary', [1, 366], 'days'];
    }

    /**
     * @param class-string $class
     * @param list<int>    $args
     */
    #[DataProvider('invalidArguments')]
    public function testRangesAreValidatedBeforeTheHttpCall(string $class, string $method, array $args, string $parameter): void
    {
        $this->expectToolError('Невірні параметри: '.$parameter, fn () => $this->call($class, $method, $args));
        self::assertCount(0, $this->responder()->backendRequests());
    }

    /**
     * @return iterable<string, array{class-string, string, list<int>, string}>
     */
    public static function boundaryArguments(): iterable
    {
        yield 'users login days=90' => [UsersTools::class, 'getLoginActivity', [7, 90], 'http://users-service/internal/ai/v1/tenants/7/login-activity?days=90'];
        yield 'users events limit=1' => [UsersTools::class, 'getSecurityEvents', [7, 1], 'http://users-service/internal/ai/v1/tenants/7/security-events?limit=1'];
        yield 'billing churn limit=200' => [BillingTools::class, 'getChurnRisk', [7, 200], 'http://billing-service/internal/ai/v1/tenants/7/churn-risk?limit=200'];
        yield 'billing revenue days=365' => [BillingTools::class, 'getRevenueSummary', [7, 365], 'http://billing-service/internal/ai/v1/tenants/7/revenue-summary?days=365'];
    }

    /**
     * @param class-string $class
     * @param list<int>    $args
     */
    #[DataProvider('boundaryArguments')]
    public function testBoundaryValuesArePassedAsQuery(string $class, string $method, array $args, string $url): void
    {
        $this->responder()->queue(Fixtures::response(Fixtures::USERS, 'users-summary'));

        $this->call($class, $method, $args);

        self::assertSame($url, $this->responder()->backendRequests()[0]['url']);
    }

    /**
     * @param class-string $class
     * @param list<int>    $args
     *
     * @return array<mixed>
     */
    private function call(string $class, string $method, array $args): array
    {
        $tool = $this->service($class);
        $result = $tool->{$method}(...$args);
        self::assertIsArray($result);

        return $result;
    }

    private function expectToolError(string $message, \Closure $call): ToolCallException
    {
        try {
            $call();
        } catch (ToolCallException $e) {
            self::assertSame($message, $e->getMessage());

            return $e;
        }

        self::fail('Expected ToolCallException with message: '.$message);
    }
}
