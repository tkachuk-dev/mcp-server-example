<?php

declare(strict_types=1);

namespace App\Tests\Integration\Mcp;

use App\Mcp\AuditLogger;
use App\Mcp\Tool\BillingTools;
use App\Mcp\Tool\UsersTools;
use App\Observability\TraceContext;
use App\Tests\Support\Fixtures;
use App\Tests\Support\HttpMockResponder;
use App\Tests\Support\ToolTestCase;
use Mcp\Exception\ToolCallException;

final class TracingAndAuditTest extends ToolTestCase
{
    private const string INCOMING_TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';

    public function testKeepsIncomingTraceAndRequestIdAndOpensNewSpan(): void
    {
        $incoming = '00-'.self::INCOMING_TRACE_ID.'-00f067aa0ba902b7-01';
        $this->service(TraceContext::class)->startRequest($incoming, 'req-123');
        $this->responder()->queue(Fixtures::response(Fixtures::USERS, 'users-summary'), Fixtures::response(Fixtures::BILLING, 'rfm-summary'));

        $this->service(UsersTools::class)->getSummary(1);
        $this->service(BillingTools::class)->getRfmSummary(1);

        [$users, $billing] = $this->responder()->backendRequests();
        foreach ([$users, $billing] as $request) {
            $traceparent = (string) HttpMockResponder::header($request, 'traceparent');
            self::assertMatchesRegularExpression(self::TRACEPARENT_PATTERN, $traceparent);
            self::assertStringStartsWith('00-'.self::INCOMING_TRACE_ID.'-', $traceparent);
            self::assertNotSame($incoming, $traceparent);
            self::assertSame('req-123', HttpMockResponder::header($request, 'X-Request-Id'));
        }
        self::assertNotSame(HttpMockResponder::header($users, 'traceparent'), HttpMockResponder::header($billing, 'traceparent'));
    }

    public function testGeneratesTraceContextWhenNoneCameIn(): void
    {
        $this->responder()->queue(Fixtures::response(Fixtures::USERS, 'users-summary'));

        $this->service(UsersTools::class)->getSummary(1);

        $request = $this->responder()->backendRequests()[0];
        self::assertMatchesRegularExpression(self::TRACEPARENT_PATTERN, (string) HttpMockResponder::header($request, 'traceparent'));
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) HttpMockResponder::header($request, 'X-Request-Id'));
    }

    public function testWritesExactlyOneAuditEntryPerSuccessfulCall(): void
    {
        $this->responder()->queue(Fixtures::response(Fixtures::BILLING, 'churn-risk'));

        $this->service(BillingTools::class)->getChurnRisk(5, 10);

        $records = $this->auditRecords();
        self::assertCount(1, $records);
        self::assertSame(AuditLogger::MESSAGE, $records[0]->message);
        self::assertSame([
            'email' => self::ACTING_USER,
            'tool' => 'billing_get_churn_risk',
            'service' => 'billing',
            'arguments' => ['tenantId' => 5, 'limit' => 10],
            'result_status' => 'success',
        ], array_diff_key($records[0]->context, ['duration_ms' => true]));
        self::assertIsInt($records[0]->context['duration_ms']);
        self::assertArrayHasKey('request_id', $records[0]->extra);
        self::assertArrayHasKey('trace_id', $records[0]->extra);
    }

    public function testAuditsFailedCallsAsToolErrorWithoutSecrets(): void
    {
        $this->responder()->queue(Fixtures::status(500));

        try {
            $this->service(UsersTools::class)->getSecurityEvents(1);
            self::fail('Expected ToolCallException');
        } catch (ToolCallException) {
        }

        $records = $this->auditRecords();
        self::assertCount(1, $records);
        self::assertSame('tool_error', $records[0]->context['result_status']);
        $encoded = json_encode([$records[0]->context, $records[0]->extra], \JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString(self::USERS_TOKEN, $encoded);
        self::assertStringNotContainsString('internal details', $encoded);
    }

    public function testAuditsValidationErrors(): void
    {
        try {
            $this->service(UsersTools::class)->getLoginActivity(1, 500);
            self::fail('Expected ToolCallException');
        } catch (ToolCallException) {
        }

        $records = $this->auditRecords();
        self::assertCount(1, $records);
        self::assertSame('tool_error', $records[0]->context['result_status']);
        self::assertSame(['tenantId' => 1, 'days' => 500], $records[0]->context['arguments']);
    }
}
