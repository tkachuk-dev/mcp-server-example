<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Dto\ToolInvocation;
use App\Enums\AuditResult;
use App\Exception\BackendException;
use App\Observability\TraceContext;
use Mcp\Exception\ToolCallException;

/**
 * Wraps every tools/call: new trace span, exactly one audit entry, and domain errors
 * turned into ToolCallException — the only exception the SDK reports as "isError: true".
 */
final readonly class ToolExecutor
{
    private const int NANOSECONDS_PER_MS = 1_000_000;

    public function __construct(
        private TraceContext $traceContext,
        private AuditLogger $auditLogger,
    ) {
    }

    /**
     * @param \Closure(): array<mixed> $call
     *
     * @return array<mixed>
     */
    public function run(ToolInvocation $invocation, \Closure $call): array
    {
        $this->traceContext->startToolCall();
        $startedAt = hrtime(true);
        $result = AuditResult::InternalError;

        try {
            $payload = $call();
            $result = AuditResult::Success;

            return $payload;
        } catch (BackendException $e) {
            $result = AuditResult::ToolError;

            throw new ToolCallException($e->getMessage(), 0, $e);
        } finally {
            $this->auditLogger->log($invocation, $result, intdiv(hrtime(true) - $startedAt, self::NANOSECONDS_PER_MS));
        }
    }
}
