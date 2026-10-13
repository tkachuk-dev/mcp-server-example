<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

// Priority -64: after the security exception listener (1) and exception logging (0),
// before Symfony's HTML/problem error renderer (-128).
#[AsEventListener(priority: -64)]
class ApiExceptionListener
{
    private const string VALIDATION_MESSAGE = 'Validation failed.';

    public function __construct(
        #[Autowire(service: 'serializer.name_converter.camel_case_to_snake_case')]
        private readonly NameConverterInterface $nameConverter,
        #[Autowire(param: 'kernel.debug')]
        private readonly bool $debug,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $response = match (true) {
            $exception instanceof HttpExceptionInterface => $this->fromHttpException($exception),
            null !== $this->declaredStatus($exception) => $this->message($exception->getMessage(), (int) $this->declaredStatus($exception)),
            !$this->debug => $this->message(Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR], Response::HTTP_INTERNAL_SERVER_ERROR),
            default => null,
        };

        if (null !== $response) {
            $event->setResponse($response);
        }
    }

    private function fromHttpException(HttpExceptionInterface $exception): JsonResponse
    {
        $previous = $exception->getPrevious();
        if ($previous instanceof ValidationFailedException) {
            return $this->validationFailed($previous, $exception->getStatusCode());
        }

        // Domain exceptions (#[WithHttpStatus]) are wrapped by Symfony's ErrorListener; their message is safe to expose.
        // Framework-generated messages (router, security) can echo request details: use the generic status text.
        $message = null !== $previous && null !== $this->declaredStatus($previous)
            ? $previous->getMessage()
            : Response::$statusTexts[$exception->getStatusCode()] ?? 'Error';
        $response = $this->message($message, $exception->getStatusCode());
        $response->headers->add($exception->getHeaders());

        return $response;
    }

    private function validationFailed(ValidationFailedException $exception, int $status): JsonResponse
    {
        $errors = [];
        foreach ($exception->getViolations() as $violation) {
            $errors[$this->nameConverter->normalize($violation->getPropertyPath())][] = (string) $violation->getMessage();
        }

        return new JsonResponse(['message' => self::VALIDATION_MESSAGE, 'errors' => $errors], $status);
    }

    private function declaredStatus(\Throwable $exception): ?int
    {
        $attributes = (new \ReflectionClass($exception))->getAttributes(WithHttpStatus::class);

        return [] === $attributes ? null : $attributes[0]->newInstance()->statusCode;
    }

    private function message(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }
}
