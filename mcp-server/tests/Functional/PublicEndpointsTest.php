<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\McpWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PublicEndpointsTest extends McpWebTestCase
{
    private const array METADATA = [
        'resource' => 'http://localhost/mcp',
        'authorization_servers' => ['https://accounts.google.com'],
        'scopes_supported' => ['openid', 'email'],
        'bearer_methods_supported' => ['header'],
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function metadataPaths(): iterable
    {
        yield 'root' => ['/.well-known/oauth-protected-resource'];
        yield 'path-suffixed (RFC 9728)' => ['/.well-known/oauth-protected-resource/mcp'];
    }

    #[DataProvider('metadataPaths')]
    public function testProtectedResourceMetadata(string $path): void
    {
        $this->client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(self::METADATA, $this->jsonResponse());
    }

    public function testLiveness(): void
    {
        $this->client->request('GET', '/health/live');

        self::assertResponseIsSuccessful();
        self::assertSame(['service' => 'mcp-server'], $this->jsonResponse());
    }

    public function testReadinessDoesNotDependOnBackends(): void
    {
        // No backend responses queued: any backend call would throw.
        $this->client->request('GET', '/health/ready');

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->responder()->backendRequests());
    }
}
