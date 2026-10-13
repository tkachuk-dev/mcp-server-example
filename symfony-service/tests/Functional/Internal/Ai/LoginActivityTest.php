<?php

declare(strict_types=1);

namespace App\Tests\Functional\Internal\Ai;

use App\Dto\Ai\Request\LoginActivityQuery;
use App\Tests\Support\InternalAiWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class LoginActivityTest extends InternalAiWebTestCase
{
    public function testDefaultsToThirtyZeroFilledAscendingDays(): void
    {
        $response = $this->mcpGet(self::BASE_PATH.'/1/login-activity');

        self::assertSame(200, $response->getStatusCode());
        $this->assertMatchesSchema('LoginActivity', $response);

        $body = $this->json($response);
        $today = (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))->format('Y-m-d');
        self::assertSame(LoginActivityQuery::DEFAULT_DAYS, $body['days']);
        self::assertCount(LoginActivityQuery::DEFAULT_DAYS, $body['daily']);
        self::assertSame($today, $body['period_end']);
        self::assertSame($body['period_start'], $body['daily'][0]['date']);
        self::assertSame($today, $body['daily'][29]['date']);
    }

    public function testAggregatesMatchFixtures(): void
    {
        $body = $this->json($this->mcpGet(self::BASE_PATH.'/1/login-activity?days=7'));

        self::assertCount(7, $body['daily']);
        // Fixtures: (d % 3) + 1 successful logins by distinct users, one failure on even offsets,
        // password resets on offsets 1, 2 and 5.
        self::assertSame(13, array_sum(array_column($body['daily'], 'successful_logins')));
        self::assertSame(13, array_sum(array_column($body['daily'], 'unique_active_users')));
        self::assertSame(4, array_sum(array_column($body['daily'], 'failed_logins')));
        self::assertSame(3, array_sum(array_column($body['daily'], 'password_resets')));

        $todayEntry = $body['daily'][6];
        self::assertSame(['successful_logins' => 1, 'failed_logins' => 1, 'unique_active_users' => 1, 'password_resets' => 0], array_diff_key($todayEntry, ['date' => true]));
    }

    #[DataProvider('invalidDays')]
    public function testInvalidDaysIsUnprocessable(string $days): void
    {
        $response = $this->mcpGet(self::BASE_PATH.'/1/login-activity?days='.$days);

        self::assertSame(422, $response->getStatusCode());
        $this->assertMatchesSchema('ValidationError', $response);
        self::assertArrayHasKey('days', $this->json($response)['errors']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDays(): iterable
    {
        yield 'too large' => ['1000'];
        yield 'zero' => ['0'];
        yield 'not a number' => ['abc'];
    }

    public function testUnknownTenantIsNotFound(): void
    {
        self::assertSame(404, $this->mcpGet(self::BASE_PATH.'/999/login-activity')->getStatusCode());
    }
}
