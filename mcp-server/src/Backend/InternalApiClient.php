<?php

declare(strict_types=1);

namespace App\Backend;

use App\Enums\BackendService;
use App\Exception\BackendException;
use App\Exception\BackendUnavailableException;
use App\Exception\InvalidArgumentsException;
use App\Exception\TenantNotFoundException;
use App\Observability\TraceContext;
use App\Security\ActingUser;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The only place that talks to the backends' internal API. One instance per backend
 * (see services.yaml): scoped client with base URI, service token, timeouts and retries.
 */
final readonly class InternalApiClient
{
    public const string API_PREFIX = '/internal/ai/v1/';
    public const string HEADER_ACTING_USER = 'X-Acting-User';

    private const string UNKNOWN_PARAMETER = 'запит';
    private const int NANOSECONDS_PER_MS = 1_000_000;

    public function __construct(
        private HttpClientInterface $httpClient,
        private BackendService $service,
        private ActingUser $actingUser,
        private TraceContext $traceContext,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, int> $query
     *
     * @return array<mixed>
     *
     * @throws BackendException
     */
    public function get(string $path, array $query = []): array
    {
        $startedAt = hrtime(true);

        try {
            $response = $this->httpClient->request('GET', self::API_PREFIX.$path, [
                'query' => $query,
                'headers' => $this->headers(),
            ]);

            return $this->decode($response);
        } catch (HttpClientException $e) {
            throw new BackendUnavailableException($this->service, $e);
        } finally {
            $this->logger->info('Backend call', [
                'service' => $this->service->value,
                'path' => $path,
                'duration_ms' => intdiv(hrtime(true) - $startedAt, self::NANOSECONDS_PER_MS),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            self::HEADER_ACTING_USER => $this->actingUser->email(),
            TraceContext::HEADER_TRACEPARENT => $this->traceContext->traceparent(),
            TraceContext::HEADER_REQUEST_ID => $this->traceContext->requestId(),
        ];
    }

    /**
     * @return array<mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $status = $response->getStatusCode();

        if ($status < Response::HTTP_BAD_REQUEST) {
            return $response->toArray();
        }

        $this->logger->warning('Backend returned an error', [
            'service' => $this->service->value,
            'status' => $status,
        ]);

        throw match ($status) {
            Response::HTTP_NOT_FOUND => new TenantNotFoundException(),
            Response::HTTP_UNPROCESSABLE_ENTITY => new InvalidArgumentsException($this->invalidParameter($response)),
            default => new BackendUnavailableException($this->service),
        };
    }

    /**
     * Only the parameter name is taken from a 422 body; the body itself never reaches the model.
     */
    private function invalidParameter(ResponseInterface $response): string
    {
        try {
            $errors = $response->toArray(false)['errors'] ?? null;
        } catch (HttpClientException) {
            return self::UNKNOWN_PARAMETER;
        }

        if (!\is_array($errors) || [] === $errors) {
            return self::UNKNOWN_PARAMETER;
        }

        $parameter = (string) array_key_first($errors);

        return 1 === preg_match('/^[a-z_]{1,64}$/i', $parameter) ? $parameter : self::UNKNOWN_PARAMETER;
    }
}
