<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\McpWebTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AuthTest extends McpWebTestCase
{
    private const string METADATA_URL = 'http://localhost/.well-known/oauth-protected-resource';

    public function testRequestWithoutTokenGets401WithResourceMetadata(): void
    {
        $this->client->request('POST', self::MCP_PATH, server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer resource_metadata="'.self::METADATA_URL.'"');
        self::assertSame(['message' => 'Потрібна автентифікація'], $this->jsonResponse());
    }

    public function testInvalidTokenGets401InvalidToken(): void
    {
        $this->responder()->setGoogleTokenInfo(['aud' => 'another-app', 'email' => self::ACTING_USER, 'email_verified' => 'true']);

        $this->postMcp(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer error="invalid_token", resource_metadata="'.self::METADATA_URL.'"');
    }

    public function testTokenUnknownToGoogleGets401(): void
    {
        $this->responder()->useQueueForGoogle();
        $this->responder()->queue(new MockResponse('{"error":"invalid_token"}', ['http_code' => 400]));

        $this->postMcp(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testForeignOriginIsForbiddenEvenBeforeAuthentication(): void
    {
        $this->client->request('POST', self::MCP_PATH, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ORIGIN' => 'https://evil.example',
        ], content: '{}');

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['message' => 'Origin заборонено'], $this->jsonResponse());
    }

    public function testAllowlistedOriginPasses(): void
    {
        $this->postMcp(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'], server: ['HTTP_ORIGIN' => 'https://claude.ai']);

        self::assertResponseStatusCodeSame(400); // past Origin and auth; the SDK wants an initialized session
    }

    public function testRateLimitIs60RequestsPerMinutePerUser(): void
    {
        for ($i = 1; $i <= 60; ++$i) {
            $this->client->request('GET', self::MCP_PATH, server: ['HTTP_AUTHORIZATION' => 'Bearer '.self::ACCESS_TOKEN]);
            self::assertNotSame(429, $this->client->getResponse()->getStatusCode(), 'Request #'.$i);
        }

        $this->client->request('GET', self::MCP_PATH, server: ['HTTP_AUTHORIZATION' => 'Bearer '.self::ACCESS_TOKEN]);

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');
    }

    public function testEveryResponseCarriesRequestId(): void
    {
        $this->client->request('GET', '/health/live', server: ['HTTP_X_REQUEST_ID' => 'probe-1']);
        self::assertResponseHeaderSame('X-Request-Id', 'probe-1');

        $this->client->request('POST', self::MCP_PATH);
        self::assertResponseStatusCodeSame(401);
        self::assertResponseHasHeader('X-Request-Id');
    }
}
