<?php

declare(strict_types=1);

namespace App\Tests\Functional\Internal\Ai;

use App\Tests\Support\InternalAiWebTestCase;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final class RateLimitTest extends InternalAiWebTestCase
{
    private const int LIMIT_PER_MINUTE = 120;

    public function testExceedingLimitReturnsTooManyRequests(): void
    {
        $actor = sprintf('rate-limit-%s@example.com', bin2hex(random_bytes(4)));
        /** @var RateLimiterFactoryInterface $limiter */
        $limiter = static::getContainer()->get('limiter.internal_ai');
        $limiter->create('mcp:'.$actor)->consume(self::LIMIT_PER_MINUTE);

        $this->client->request('GET', self::BASE_PATH.'/1/users-summary', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.self::SERVICE_TOKEN,
            'HTTP_X_ACTING_USER' => $actor,
        ]);

        $response = $this->client->getResponse();
        self::assertSame(429, $response->getStatusCode());
        self::assertTrue($response->headers->has('Retry-After'));
        $this->assertMatchesSchema('Error', $response);
    }

    public function testLimitIsTrackedPerActor(): void
    {
        /** @var RateLimiterFactoryInterface $limiter */
        $limiter = static::getContainer()->get('limiter.internal_ai');
        $limiter->create('mcp:exhausted@example.com')->consume(self::LIMIT_PER_MINUTE);

        self::assertSame(200, $this->mcpGet(self::BASE_PATH.'/1/users-summary')->getStatusCode());
    }
}
