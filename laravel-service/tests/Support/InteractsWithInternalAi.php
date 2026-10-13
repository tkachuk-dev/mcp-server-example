<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

trait InteractsWithInternalAi
{
    protected const string SERVICE_TOKEN = 'test-mcp-token';

    protected const string ACTING_USER = 'analyst@example.com';

    protected function setUpInternalAi(): void
    {
        config(['internal_ai.token_hashes' => [hash('sha256', self::SERVICE_TOKEN)]]);
    }

    /**
     * @return array<string, string>
     */
    protected function mcpHeaders(string $token = self::SERVICE_TOKEN, string $actingUser = self::ACTING_USER): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'X-Acting-User' => $actingUser,
            'Accept' => 'application/json',
        ];
    }

    /**
     * @return TestResponse<Response>
     */
    protected function mcpGet(string $uri): TestResponse
    {
        return $this->getJson($uri, $this->mcpHeaders());
    }
}
