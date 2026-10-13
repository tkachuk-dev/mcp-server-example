<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

class TraceContextListener
{
    public const string REQUEST_ID_HEADER = 'X-Request-Id';
    public const string TRACEPARENT_HEADER = 'traceparent';
    public const string ATTR_REQUEST_ID = '_request_id';
    public const string ATTR_TRACEPARENT = '_traceparent';
    public const string ATTR_TRACE_ID = '_trace_id';
    public const string ATTR_STARTED_AT = '_request_started_at';

    private const string TRACEPARENT_PATTERN = '/^00-([0-9a-f]{32})-[0-9a-f]{16}-[0-9a-f]{2}$/';
    private const string REQUEST_ID_PATTERN = '/^[A-Za-z0-9._-]{1,128}$/';
    private const string INVALID_TRACE_ID = '00000000000000000000000000000000';

    // Runs before the router and the firewall so that even 401/404 responses are traced.
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 512)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $traceparent = $this->resolveTraceparent($request->headers->get(self::TRACEPARENT_HEADER));

        $request->attributes->set(self::ATTR_STARTED_AT, hrtime(true));
        $request->attributes->set(self::ATTR_TRACEPARENT, $traceparent);
        $request->attributes->set(self::ATTR_TRACE_ID, substr($traceparent, 3, 32));
        $request->attributes->set(self::ATTR_REQUEST_ID, $this->resolveRequestId($request->headers->get(self::REQUEST_ID_HEADER)));
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onResponse(ResponseEvent $event): void
    {
        $requestId = $event->getRequest()->attributes->get(self::ATTR_REQUEST_ID);
        if ($event->isMainRequest() && \is_string($requestId)) {
            $event->getResponse()->headers->set(self::REQUEST_ID_HEADER, $requestId);
        }
    }

    private function resolveTraceparent(?string $incoming): string
    {
        if (null !== $incoming && 1 === preg_match(self::TRACEPARENT_PATTERN, $incoming, $m) && self::INVALID_TRACE_ID !== $m[1]) {
            return $incoming;
        }

        return \sprintf('00-%s-%s-01', bin2hex(random_bytes(16)), bin2hex(random_bytes(8)));
    }

    private function resolveRequestId(?string $incoming): string
    {
        if (null !== $incoming && 1 === preg_match(self::REQUEST_ID_PATTERN, $incoming)) {
            return $incoming;
        }

        return Uuid::v4()->toRfc4122();
    }
}
