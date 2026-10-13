<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * 401 for requests without a token; the header lets MCP clients discover the authorization server.
 */
final readonly class McpAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public const string MESSAGE = 'Потрібна автентифікація';

    public function __construct(
        private ProtectedResourceMetadata $metadata,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['message' => self::MESSAGE], Response::HTTP_UNAUTHORIZED, [
            'WWW-Authenticate' => \sprintf('Bearer resource_metadata="%s"', $this->metadata->metadataUrl()),
        ]);
    }
}
