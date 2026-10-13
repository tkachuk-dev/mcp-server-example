<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * DNS rebinding protection for the MCP endpoint: a browser-sent Origin must be allowlisted.
 * Replaces the SDK's own check (mcp.yaml: allowed_hosts: false), which only knows localhost.
 */
final readonly class OriginCheckListener
{
    public const string MESSAGE = 'Origin заборонено';
    public const string MCP_ROUTE_PREFIX = '_mcp_endpoint_';

    // After the router (32) has set _route, before the firewall (8).
    private const int PRIORITY = 16;

    /**
     * @param list<string> $allowedOrigins
     */
    public function __construct(
        #[Autowire(env: 'csv:MCP_ALLOWED_ORIGINS')]
        private array $allowedOrigins,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: self::PRIORITY)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route', '');
        $origin = $request->headers->get('Origin');

        if (!str_starts_with($route, self::MCP_ROUTE_PREFIX) || null === $origin || $this->isAllowed($origin)) {
            return;
        }

        $event->setResponse(new JsonResponse(['message' => self::MESSAGE], Response::HTTP_FORBIDDEN));
    }

    private function isAllowed(string $origin): bool
    {
        $normalized = array_map(self::normalize(...), $this->allowedOrigins);

        return \in_array(self::normalize($origin), $normalized, true);
    }

    private static function normalize(string $origin): string
    {
        return rtrim(strtolower(trim($origin)), '/');
    }
}
