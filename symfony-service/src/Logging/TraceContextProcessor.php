<?php

declare(strict_types=1);

namespace App\Logging;

use App\EventListener\TraceContextListener;
use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

// Stateless: reads the current main request on every record, so it is safe in FrankenPHP worker mode.
#[AsMonologProcessor]
class TraceContextProcessor implements ProcessorInterface
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $attributes = $this->requestStack->getMainRequest()?->attributes;
        if (null === $attributes || !$attributes->has(TraceContextListener::ATTR_REQUEST_ID)) {
            return $record;
        }

        $record->extra['request_id'] = $attributes->get(TraceContextListener::ATTR_REQUEST_ID);
        $record->extra['trace_id'] = $attributes->get(TraceContextListener::ATTR_TRACE_ID);
        $record->extra['traceparent'] = $attributes->get(TraceContextListener::ATTR_TRACEPARENT);

        return $record;
    }
}
