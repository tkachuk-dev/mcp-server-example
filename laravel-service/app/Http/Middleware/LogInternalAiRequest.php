<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit log for /internal/ai, written after the response is sent (terminable middleware).
 */
class LogInternalAiRequest
{
    public const string LOG_CHANNEL = 'ai_audit';

    private const string STARTED_AT_ATTRIBUTE = 'internal_ai_started_at';

    private const string ANONYMOUS_ACTOR = 'anonymous';

    private const int NANOSECONDS_IN_MILLISECOND = 1_000_000;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::STARTED_AT_ATTRIBUTE, hrtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        Log::channel(self::LOG_CHANNEL)->info('internal_ai_request', [
            'actor' => $request->attributes->get(AuthenticateMcpService::ACTOR_ATTRIBUTE, self::ANONYMOUS_ACTOR),
            'path' => $request->path(),
            'query' => $request->query(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $this->durationMs($request),
        ]);
    }

    private function durationMs(Request $request): float
    {
        $startedAt = (int) $request->attributes->get(self::STARTED_AT_ATTRIBUTE, hrtime(true));

        return round((hrtime(true) - $startedAt) / self::NANOSECONDS_IN_MILLISECOND, 2);
    }
}
