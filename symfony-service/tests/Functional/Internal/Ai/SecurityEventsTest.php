<?php

declare(strict_types=1);

namespace App\Tests\Functional\Internal\Ai;

use App\DataFixtures\AppFixtures;
use App\Dto\Ai\Request\SecurityEventsQuery;
use App\Tests\Support\InternalAiWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SecurityEventsTest extends InternalAiWebTestCase
{
    public function testReturnsLatestEventsFirst(): void
    {
        $response = $this->mcpGet(self::BASE_PATH.'/1/security-events');

        self::assertSame(200, $response->getStatusCode());
        $this->assertMatchesSchema('SecurityEvents', $response);

        $body = $this->json($response);
        self::assertSame(SecurityEventsQuery::DEFAULT_LIMIT, $body['limit']);
        self::assertCount(AppFixtures::TENANT_ONE_SECURITY_EVENTS, $body['events']);
        self::assertSame('failed_login_burst', $body['events'][0]['type']);
        self::assertNull($body['events'][0]['user_id']);
        self::assertSame('account_locked', $body['events'][1]['type']);
        self::assertIsInt($body['events'][1]['user_id']);

        $times = array_column($body['events'], 'occurred_at');
        $sorted = $times;
        rsort($sorted);
        self::assertSame($sorted, $times);
    }

    public function testLimitIsApplied(): void
    {
        $body = $this->json($this->mcpGet(self::BASE_PATH.'/1/security-events?limit=3'));

        self::assertSame(3, $body['limit']);
        self::assertCount(3, $body['events']);
    }

    public function testDataIsIsolatedPerTenant(): void
    {
        $body = $this->json($this->mcpGet(self::BASE_PATH.'/2/security-events'));

        self::assertCount(1, $body['events']);
        self::assertSame('role_changed', $body['events'][0]['type']);
    }

    #[DataProvider('invalidLimits')]
    public function testInvalidLimitIsUnprocessable(string $limit): void
    {
        $response = $this->mcpGet(self::BASE_PATH.'/1/security-events?limit='.$limit);

        self::assertSame(422, $response->getStatusCode());
        $this->assertMatchesSchema('ValidationError', $response);
        self::assertArrayHasKey('limit', $this->json($response)['errors']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLimits(): iterable
    {
        yield 'too large' => ['1000'];
        yield 'zero' => ['0'];
        yield 'not a number' => ['many'];
    }

    public function testUnknownTenantIsNotFound(): void
    {
        self::assertSame(404, $this->mcpGet(self::BASE_PATH.'/999/security-events')->getStatusCode());
    }
}
