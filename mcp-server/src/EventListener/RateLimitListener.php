<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Security\ActingUser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Per-user limit on the MCP endpoint (60/min, config/packages/framework.yaml).
 */
final readonly class RateLimitListener
{
    public const string MESSAGE = 'Забагато запитів, спробуйте пізніше';

    // After the firewall (8), so the user is already authenticated.
    private const int PRIORITY = 0;

    public function __construct(
        #[Autowire(service: 'limiter.mcp_user')]
        private RateLimiterFactoryInterface $limiterFactory,
        private ActingUser $actingUser,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: self::PRIORITY)]
    public function onRequest(RequestEvent $event): void
    {
        $route = (string) $event->getRequest()->attributes->get('_route', '');
        if (!$event->isMainRequest() || !str_starts_with($route, OriginCheckListener::MCP_ROUTE_PREFIX)) {
            return;
        }

        $limit = $this->limiterFactory->create($this->actingUser->email())->consume();
        if ($limit->isAccepted()) {
            return;
        }

        $event->setResponse(new JsonResponse(['message' => self::MESSAGE], Response::HTTP_TOO_MANY_REQUESTS, [
            'Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time()),
        ]));
    }
}
