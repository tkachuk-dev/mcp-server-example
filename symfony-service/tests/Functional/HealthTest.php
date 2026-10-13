<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\InternalAiWebTestCase;

final class HealthTest extends InternalAiWebTestCase
{
    public function testLivenessNeedsNoToken(): void
    {
        $this->client->request('GET', '/health/live');

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        $this->assertMatchesSchema('Health', $response);
    }

    public function testReadinessChecksDependencies(): void
    {
        $this->client->request('GET', '/health/ready');

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['message' => 'OK'], $this->json($response));
    }
}
