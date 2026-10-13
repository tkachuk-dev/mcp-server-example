<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\InternalAiWebTestCase;

final class TraceContextTest extends InternalAiWebTestCase
{
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    public function testIncomingRequestIdIsEchoed(): void
    {
        $response = $this->mcpGet(self::BASE_PATH.'/1/users-summary', [
            'HTTP_X_REQUEST_ID' => 'req-123',
            'HTTP_TRACEPARENT' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
        ]);

        self::assertSame('req-123', $response->headers->get('X-Request-Id'));
    }

    public function testRequestIdIsGeneratedWhenMissing(): void
    {
        $response = $this->mcpGet(self::BASE_PATH.'/1/users-summary');

        self::assertMatchesRegularExpression(self::UUID_PATTERN, (string) $response->headers->get('X-Request-Id'));
    }

    public function testInvalidRequestIdIsReplaced(): void
    {
        $response = $this->mcpGet(self::BASE_PATH.'/1/users-summary', ['HTTP_X_REQUEST_ID' => "bad id\n<script>"]);

        self::assertMatchesRegularExpression(self::UUID_PATTERN, (string) $response->headers->get('X-Request-Id'));
    }

    public function testRequestIdIsReturnedOnErrors(): void
    {
        $this->client->request('GET', self::BASE_PATH.'/1/users-summary', server: ['HTTP_X_REQUEST_ID' => 'req-401']);

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
        self::assertSame('req-401', $this->client->getResponse()->headers->get('X-Request-Id'));
    }
}
