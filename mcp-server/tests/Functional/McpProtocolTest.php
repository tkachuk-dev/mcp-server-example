<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\Fixtures;
use App\Tests\Support\HttpMockResponder;
use App\Tests\Support\McpWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class McpProtocolTest extends McpWebTestCase
{
    private const array TOOL_NAMES = [
        'users_get_summary',
        'users_get_login_activity',
        'users_get_security_events',
        'billing_get_rfm_summary',
        'billing_get_churn_risk',
        'billing_get_revenue_summary',
    ];

    public function testInitializeReturnsSessionAndServerInfo(): void
    {
        $this->postMcp([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => self::PROTOCOL_VERSION, 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'phpunit', 'version' => '1']],
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertResponseHasHeader('Mcp-Session-Id');
        $result = $this->jsonResponse()['result'];
        self::assertSame(self::PROTOCOL_VERSION, $result['protocolVersion']);
        self::assertSame('internal-ai-mcp', $result['serverInfo']['name']);
        self::assertStringContainsString('users_*', $result['instructions']);
    }

    public function testToolsListContainsAllSixToolsWithDescriptions(): void
    {
        $session = $this->initialize();

        $this->postMcp(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], $session);

        self::assertResponseIsSuccessful();
        $tools = array_column($this->jsonResponse()['result']['tools'], null, 'name');
        self::assertEqualsCanonicalizing(self::TOOL_NAMES, array_keys($tools));
        foreach ($tools as $tool) {
            self::assertStringContainsString('PII', $tool['description']);
            self::assertTrue($tool['annotations']['readOnlyHint']);
            self::assertContains('tenantId', $tool['inputSchema']['required']);
        }
        self::assertSame(50, $tools['billing_get_churn_risk']['inputSchema']['properties']['limit']['default']);
        self::assertSame(30, $tools['users_get_login_activity']['inputSchema']['properties']['days']['default']);
    }

    /**
     * @return iterable<string, array{string, array<string, int>, string, string, string}>
     */
    public static function toolCalls(): iterable
    {
        yield 'users_get_summary' => ['users_get_summary', ['tenantId' => 1], Fixtures::USERS, 'users-summary', 'http://users-service/internal/ai/v1/tenants/1/users-summary'];
        yield 'users_get_login_activity' => ['users_get_login_activity', ['tenantId' => 1, 'days' => 30], Fixtures::USERS, 'login-activity', 'http://users-service/internal/ai/v1/tenants/1/login-activity?days=30'];
        yield 'users_get_security_events' => ['users_get_security_events', ['tenantId' => 1, 'limit' => 50], Fixtures::USERS, 'security-events', 'http://users-service/internal/ai/v1/tenants/1/security-events?limit=50'];
        yield 'billing_get_rfm_summary' => ['billing_get_rfm_summary', ['tenantId' => 1], Fixtures::BILLING, 'rfm-summary', 'http://billing-service/internal/ai/v1/tenants/1/rfm-summary'];
        yield 'billing_get_churn_risk' => ['billing_get_churn_risk', ['tenantId' => 1, 'limit' => 50], Fixtures::BILLING, 'churn-risk', 'http://billing-service/internal/ai/v1/tenants/1/churn-risk?limit=50'];
        yield 'billing_get_revenue_summary' => ['billing_get_revenue_summary', ['tenantId' => 1, 'days' => 30], Fixtures::BILLING, 'revenue-summary', 'http://billing-service/internal/ai/v1/tenants/1/revenue-summary?days=30'];
    }

    /**
     * @param array<string, int> $arguments
     */
    #[DataProvider('toolCalls')]
    public function testToolsCallReturnsBackendPayload(string $tool, array $arguments, string $fixtureService, string $fixture, string $url): void
    {
        $session = $this->initialize();
        $this->responder()->queue(Fixtures::response($fixtureService, $fixture));

        $this->callTool($session, $tool, $arguments, ['HTTP_TRACEPARENT' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01', 'HTTP_X_REQUEST_ID' => 'call-1']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Request-Id', 'call-1');
        $result = $this->jsonResponse()['result'];
        self::assertFalse($result['isError'] ?? false);
        self::assertSame(Fixtures::decode($fixtureService, $fixture), $result['structuredContent']);
        $request = $this->responder()->backendRequests()[0];
        self::assertSame($url, $request['url']);
        self::assertSame(self::ACTING_USER, HttpMockResponder::header($request, 'X-Acting-User'));
        self::assertSame('call-1', HttpMockResponder::header($request, 'X-Request-Id'));
        self::assertStringStartsWith('00-4bf92f3577b34da6a3ce929d0e0e4736-', (string) HttpMockResponder::header($request, 'traceparent'));
    }

    public function testInvalidLimitIsAToolErrorWithoutBackendCall(): void
    {
        $session = $this->initialize();

        $this->callTool($session, 'users_get_security_events', ['tenantId' => 1, 'limit' => 500]);

        $result = $this->jsonResponse()['result'];
        self::assertTrue($result['isError']);
        self::assertSame('Невірні параметри: limit', $result['content'][0]['text']);
        self::assertSame([], $this->responder()->backendRequests());
    }

    public function testBackendDownIsAToolErrorAndOtherBackendStillWorks(): void
    {
        $session = $this->initialize();
        $this->responder()->queue(Fixtures::networkError(), Fixtures::networkError(), Fixtures::response(Fixtures::USERS, 'users-summary'));

        $this->callTool($session, 'billing_get_rfm_summary', ['tenantId' => 1]);
        $billing = $this->jsonResponse()['result'];
        $this->callTool($session, 'users_get_summary', ['tenantId' => 1]);
        $users = $this->jsonResponse()['result'];

        self::assertTrue($billing['isError']);
        self::assertSame('Сервіс billing тимчасово недоступний', $billing['content'][0]['text']);
        self::assertFalse($users['isError'] ?? false);
        self::assertSame(120, $users['structuredContent']['total_users']);
    }

    public function testDeleteClosesTheSession(): void
    {
        $session = $this->initialize();

        $this->client->request('DELETE', self::MCP_PATH, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.self::ACCESS_TOKEN,
            'HTTP_MCP_SESSION_ID' => $session,
            'HTTP_MCP_PROTOCOL_VERSION' => self::PROTOCOL_VERSION,
        ]);
        self::assertResponseStatusCodeSame(200);

        $this->postMcp(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list'], $session);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @param array<string, int>    $arguments
     * @param array<string, string> $server
     */
    private function callTool(string $session, string $tool, array $arguments, array $server = []): void
    {
        $this->postMcp([
            'jsonrpc' => '2.0',
            'id' => 10,
            'method' => 'tools/call',
            'params' => ['name' => $tool, 'arguments' => $arguments],
        ], $session, $server);
    }
}
