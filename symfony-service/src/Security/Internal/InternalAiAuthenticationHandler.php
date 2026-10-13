<?php

declare(strict_types=1);

namespace App\Security\Internal;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

// Without it Symfony answers 401 with an empty body; the contract requires {"message": ...}.
class InternalAiAuthenticationHandler implements AuthenticationFailureHandlerInterface, AuthenticationEntryPointInterface
{
    private const string MESSAGE = 'Unauthorized';

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->unauthorized();
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->unauthorized();
    }

    private function unauthorized(): JsonResponse
    {
        return new JsonResponse(['message' => self::MESSAGE], Response::HTTP_UNAUTHORIZED, ['WWW-Authenticate' => 'Bearer']);
    }
}
