<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

final readonly class McpAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public const string MESSAGE = 'Невалідний або прострочений токен';

    public function __construct(
        private ProtectedResourceMetadata $metadata,
    ) {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(['message' => self::MESSAGE], Response::HTTP_UNAUTHORIZED, [
            'WWW-Authenticate' => \sprintf('Bearer error="invalid_token", resource_metadata="%s"', $this->metadata->metadataUrl()),
        ]);
    }
}
