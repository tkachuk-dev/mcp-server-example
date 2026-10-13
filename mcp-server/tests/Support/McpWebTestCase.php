<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class McpWebTestCase extends WebTestCase
{
    protected const string MCP_PATH = '/mcp';
    protected const string ACCESS_TOKEN = 'ya29.functional-test-token';
    protected const string ACTING_USER = 'alice@example.com';
    protected const string PROTOCOL_VERSION = '2025-11-25';
    protected const string CLIENT_ID = 'test-client.apps.googleusercontent.com';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // One kernel for the whole test: MCP sessions and the mock queue live in it.
        $this->client->disableReboot();
        $this->clearCachePools();
        $this->responder()->setGoogleTokenInfo([
            'aud' => self::CLIENT_ID,
            'email' => self::ACTING_USER,
            'email_verified' => 'true',
            'expires_in' => '3599',
        ]);
    }

    // Filesystem pools (as in prod) survive the per-request service reset; start each test from scratch.
    private function clearCachePools(): void
    {
        foreach (['cache.app', 'cache.rate_limiter'] as $pool) {
            $cache = static::getContainer()->get($pool);
            self::assertInstanceOf(CacheItemPoolInterface::class, $cache);
            $cache->clear();
        }
    }

    protected function responder(): HttpMockResponder
    {
        return static::getContainer()->get(HttpMockResponder::class);
    }

    /**
     * @param array<string, mixed>  $message
     * @param array<string, string> $server
     */
    protected function postMcp(array $message, ?string $sessionId = null, array $server = []): void
    {
        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer '.self::ACCESS_TOKEN,
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_MCP_PROTOCOL_VERSION' => self::PROTOCOL_VERSION,
        ];
        if (null !== $sessionId) {
            $headers['HTTP_MCP_SESSION_ID'] = $sessionId;
        }

        $this->client->request('POST', self::MCP_PATH, server: array_merge($headers, $server), content: json_encode($message, \JSON_THROW_ON_ERROR));
    }

    protected function initialize(): string
    {
        $this->postMcp([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => self::PROTOCOL_VERSION,
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
            ],
        ]);
        self::assertResponseIsSuccessful();
        $sessionId = $this->client->getResponse()->headers->get('Mcp-Session-Id');
        self::assertNotNull($sessionId);

        $this->postMcp(['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $sessionId);
        self::assertResponseStatusCodeSame(202);

        return $sessionId;
    }

    /**
     * @return array<mixed>
     */
    protected function jsonResponse(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
