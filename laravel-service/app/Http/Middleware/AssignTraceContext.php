<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts W3C traceparent and X-Request-Id (or generates them) and adds them to every log line.
 */
class AssignTraceContext
{
    public const string REQUEST_ID_HEADER = 'X-Request-Id';

    public const string TRACEPARENT_HEADER = 'traceparent';

    private const string TRACEPARENT_PATTERN = '/^00-([0-9a-f]{32})-[0-9a-f]{16}-[0-9a-f]{2}$/';

    private const string REQUEST_ID_PATTERN = '/^[A-Za-z0-9._-]{1,128}$/';

    private const string TRACEPARENT_FORMAT = '00-%s-%s-01';

    private const int TRACE_ID_BYTES = 16;

    private const int SPAN_ID_BYTES = 8;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->requestId($request);
        $traceparent = $this->traceparent($request);

        Context::add('request_id', $requestId);
        Context::add('trace_id', substr($traceparent, 3, self::TRACE_ID_BYTES * 2));
        Context::add('traceparent', $traceparent);

        $response = $next($request);
        $response->headers->set(self::REQUEST_ID_HEADER, $requestId);

        return $response;
    }

    private function requestId(Request $request): string
    {
        $incoming = (string) $request->header(self::REQUEST_ID_HEADER, '');

        return preg_match(self::REQUEST_ID_PATTERN, $incoming) === 1 ? $incoming : (string) Str::uuid();
    }

    private function traceparent(Request $request): string
    {
        $incoming = strtolower((string) $request->header(self::TRACEPARENT_HEADER, ''));

        if (preg_match(self::TRACEPARENT_PATTERN, $incoming) === 1) {
            return $incoming;
        }

        return sprintf(
            self::TRACEPARENT_FORMAT,
            bin2hex(random_bytes(self::TRACE_ID_BYTES)),
            bin2hex(random_bytes(self::SPAN_ID_BYTES)),
        );
    }
}
