<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Tests\TestCase;

class TraceContextTest extends TestCase
{
    public function test_incoming_request_id_is_echoed(): void
    {
        $this->getJson('/health/live', ['X-Request-Id' => 'req-123'])->assertHeader('X-Request-Id', 'req-123');
    }

    public function test_request_id_is_generated_when_missing(): void
    {
        $requestId = $this->getJson('/health/live')->headers->get('X-Request-Id');

        $this->assertTrue(Str::isUuid((string) $requestId));
    }

    public function test_invalid_request_id_is_replaced(): void
    {
        $requestId = $this->getJson('/health/live', ['X-Request-Id' => "bad id\n"])->headers->get('X-Request-Id');

        $this->assertTrue(Str::isUuid((string) $requestId));
    }

    public function test_request_id_is_returned_on_error_responses(): void
    {
        $this->getJson('/internal/ai/v1/tenants/1/rfm-summary', ['X-Request-Id' => 'req-401'])
            ->assertUnauthorized()
            ->assertHeader('X-Request-Id', 'req-401');
    }

    public function test_trace_context_is_added_to_log_context(): void
    {
        $traceparent = '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01';

        $this->getJson('/health/live', ['traceparent' => $traceparent, 'X-Request-Id' => 'req-ctx']);

        $this->assertSame('req-ctx', Context::get('request_id'));
        $this->assertSame('0af7651916cd43dd8448eb211c80319c', Context::get('trace_id'));
        $this->assertSame($traceparent, Context::get('traceparent'));
    }
}
