<?php

declare(strict_types=1);

namespace App\Tests\Unit\Observability;

use App\Observability\TraceContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TraceContextTest extends TestCase
{
    private const string TRACEPARENT_PATTERN = '/^00-[0-9a-f]{32}-[0-9a-f]{16}-01$/';
    private const string TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';

    public function testKeepsValidIncomingTraceIdAndRequestId(): void
    {
        $context = new TraceContext();
        $context->startRequest('00-'.self::TRACE_ID.'-00f067aa0ba902b7-01', 'abc-123');

        self::assertSame(self::TRACE_ID, $context->traceId());
        self::assertSame('abc-123', $context->requestId());
        self::assertMatchesRegularExpression(self::TRACEPARENT_PATTERN, $context->traceparent());
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function invalidInput(): iterable
    {
        yield 'missing' => [null, null];
        yield 'garbage' => ['not-a-traceparent', 'has spaces and ; semicolons'];
        yield 'all-zero trace id' => ['00-00000000000000000000000000000000-00f067aa0ba902b7-01', str_repeat('a', 129)];
    }

    #[DataProvider('invalidInput')]
    public function testGeneratesIdsForMissingOrInvalidInput(?string $traceparent, ?string $requestId): void
    {
        $context = new TraceContext();
        $context->startRequest($traceparent, $requestId);

        self::assertMatchesRegularExpression(self::TRACEPARENT_PATTERN, $context->traceparent());
        self::assertNotSame('00000000000000000000000000000000', $context->traceId());
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/', $context->requestId());
    }

    public function testEveryToolCallGetsANewSpanInTheSameTrace(): void
    {
        $context = new TraceContext();
        $context->startRequest(null, null);
        $first = $context->traceparent();

        $context->startToolCall();
        $second = $context->traceparent();

        self::assertNotSame($first, $second);
        self::assertSame(substr($first, 0, 35), substr($second, 0, 35));
    }

    public function testResetForgetsTheRequest(): void
    {
        $context = new TraceContext();
        $context->startRequest(null, 'req-1');

        $context->reset();

        self::assertFalse($context->isStarted());
        self::assertNotSame('req-1', $context->requestId());
    }
}
