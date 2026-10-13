<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Security\Internal\McpServiceTokenHandler;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

#[WithMonologChannel('ai_audit')]
class InternalAiAuditListener
{
    public const string PATH_PREFIX = '/internal/ai';
    private const string ANONYMOUS_ACTOR = 'anonymous';
    private const int NANOSECONDS_PER_MS = 1_000_000;

    public function __construct(
        #[Autowire(service: 'limiter.internal_ai')]
        private readonly RateLimiterFactoryInterface $internalAiLimiter,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {
    }

    // Priority 4: after the firewall (8), so the actor is already authenticated.
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 4)]
    public function onRequest(RequestEvent $event): void
    {
        $actor = $this->authenticatedActor();
        if (!$event->isMainRequest() || !$this->isInternalAi($event->getRequest()) || null === $actor) {
            return;
        }

        $limit = $this->internalAiLimiter->create($actor)->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - time()), 'Too Many Requests');
        }
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -16)]
    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$this->isInternalAi($request)) {
            return;
        }

        $this->logger->info('internal_ai_request', [
            'actor' => $this->authenticatedActor() ?? self::ANONYMOUS_ACTOR,
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'query' => $request->query->all(),
            'status' => $event->getResponse()->getStatusCode(),
            'duration_ms' => $this->durationMs($request),
        ]);
    }

    private function isInternalAi(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), self::PATH_PREFIX);
    }

    private function authenticatedActor(): ?string
    {
        $user = $this->security->getUser();
        if (null === $user || !\in_array(McpServiceTokenHandler::ROLE_AI_READ, $user->getRoles(), true)) {
            return null;
        }

        return $user->getUserIdentifier();
    }

    private function durationMs(Request $request): ?float
    {
        $startedAt = $request->attributes->get(TraceContextListener::ATTR_STARTED_AT);

        return \is_int($startedAt) ? round((hrtime(true) - $startedAt) / self::NANOSECONDS_PER_MS, 2) : null;
    }
}
