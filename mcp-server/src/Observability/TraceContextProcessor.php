<?php

declare(strict_types=1);

namespace App\Observability;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

#[AsMonologProcessor]
final readonly class TraceContextProcessor implements ProcessorInterface
{
    public function __construct(
        private TraceContext $traceContext,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if (!$this->traceContext->isStarted()) {
            return $record;
        }

        $record->extra['request_id'] = $this->traceContext->requestId();
        $record->extra['trace_id'] = $this->traceContext->traceId();

        return $record;
    }
}
