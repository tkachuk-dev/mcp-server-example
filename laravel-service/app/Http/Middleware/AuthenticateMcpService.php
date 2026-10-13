<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\McpAuthenticationException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Service-to-service authentication for /internal/ai. Deliberately independent of the
 * application's guards: it never logs anyone in, so it grants no rights elsewhere.
 */
class AuthenticateMcpService
{
    public const string ACTOR_ATTRIBUTE = 'mcp_actor';

    public const string ACTING_USER_HEADER = 'X-Acting-User';

    private const string ACTOR_PREFIX = 'mcp:';

    private const int MAX_EMAIL_LENGTH = 254;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->hasValidToken($request->bearerToken())) {
            throw new McpAuthenticationException('Invalid service token.');
        }

        $actor = self::ACTOR_PREFIX.$this->actingUser($request);
        $request->attributes->set(self::ACTOR_ATTRIBUTE, $actor);
        Context::add('actor', $actor);

        return $next($request);
    }

    private function hasValidToken(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $hash = hash('sha256', $token);

        foreach ((array) config('internal_ai.token_hashes') as $allowedHash) {
            if (is_string($allowedHash) && hash_equals($allowedHash, $hash)) {
                return true;
            }
        }

        return false;
    }

    private function actingUser(Request $request): string
    {
        $email = (string) $request->header(self::ACTING_USER_HEADER, '');

        if (strlen($email) > self::MAX_EMAIL_LENGTH || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new McpAuthenticationException('Missing or invalid X-Acting-User header.');
        }

        return $email;
    }
}
