<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Observability\TraceContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class TraceContextListener
{
    // Before the router (32) and the firewall (8), so even early 401/403/404 responses carry the ids.
    private const int REQUEST_PRIORITY = 256;

    public function __construct(
        private TraceContext $traceContext,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: self::REQUEST_PRIORITY)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getRequest()->headers;
        $this->traceContext->startRequest(
            $headers->get(TraceContext::HEADER_TRACEPARENT),
            $headers->get(TraceContext::HEADER_REQUEST_ID),
        );
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $event->getResponse()->headers->set(TraceContext::HEADER_REQUEST_ID, $this->traceContext->requestId());
    }
}
