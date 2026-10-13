<?php

declare(strict_types=1);

namespace App\Observability;

use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

/**
 * W3C Trace Context + X-Request-Id of the current HTTP request. Reset between requests (worker mode).
 */
final class TraceContext implements ResetInterface
{
    public const string HEADER_TRACEPARENT = 'traceparent';
    public const string HEADER_REQUEST_ID = 'X-Request-Id';

    private const string VERSION = '00';
    private const string FLAGS_SAMPLED = '01';
    private const string TRACEPARENT_PATTERN = '/^00-([0-9a-f]{32})-[0-9a-f]{16}-[0-9a-f]{2}$/';
    private const string REQUEST_ID_PATTERN = '/^[A-Za-z0-9._-]{1,128}$/';
    private const string INVALID_TRACE_ID = '00000000000000000000000000000000';
    private const int TRACE_ID_BYTES = 16;
    private const int SPAN_ID_BYTES = 8;

    private ?string $traceId = null;
    private ?string $spanId = null;
    private ?string $requestId = null;

    public function startRequest(?string $traceparent, ?string $requestId): void
    {
        $this->traceId = $this->parseTraceId($traceparent) ?? self::randomHex(self::TRACE_ID_BYTES);
        $this->spanId = self::randomHex(self::SPAN_ID_BYTES);
        $this->requestId = $this->isValidRequestId($requestId) ? $requestId : Uuid::v4()->toRfc4122();
    }

    /**
     * Every tools/call is its own span inside the caller's trace.
     */
    public function startToolCall(): void
    {
        $this->ensureStarted();
        $this->spanId = self::randomHex(self::SPAN_ID_BYTES);
    }

    public function isStarted(): bool
    {
        return null !== $this->traceId;
    }

    public function traceId(): string
    {
        $this->ensureStarted();

        return (string) $this->traceId;
    }

    public function requestId(): string
    {
        $this->ensureStarted();

        return (string) $this->requestId;
    }

    public function traceparent(): string
    {
        $this->ensureStarted();

        return \sprintf('%s-%s-%s-%s', self::VERSION, $this->traceId, $this->spanId, self::FLAGS_SAMPLED);
    }

    public function reset(): void
    {
        $this->traceId = null;
        $this->spanId = null;
        $this->requestId = null;
    }

    private function ensureStarted(): void
    {
        if (!$this->isStarted()) {
            $this->startRequest(null, null);
        }
    }

    private function parseTraceId(?string $traceparent): ?string
    {
        if (null === $traceparent || 1 !== preg_match(self::TRACEPARENT_PATTERN, $traceparent, $matches)) {
            return null;
        }

        return self::INVALID_TRACE_ID === $matches[1] ? null : $matches[1];
    }

    /**
     * @phpstan-assert-if-true string $requestId
     */
    private function isValidRequestId(?string $requestId): bool
    {
        return null !== $requestId && 1 === preg_match(self::REQUEST_ID_PATTERN, $requestId);
    }

    /**
     * @param positive-int $bytes
     */
    private static function randomHex(int $bytes): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
