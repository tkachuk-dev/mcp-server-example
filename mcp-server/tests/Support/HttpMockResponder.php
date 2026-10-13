<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Response factory of the test MockHttpClient (framework.http_client.mock_response_factory).
 * Google tokeninfo is answered from a fixed payload; backend calls consume a FIFO queue.
 */
final class HttpMockResponder
{
    public const string GOOGLE_HOST = 'oauth2.googleapis.com';

    /** @var list<MockResponse> */
    private array $queue = [];

    /** @var list<array{method: string, url: string, options: array<string, mixed>}> */
    private array $requests = [];

    /** @var array<string, mixed>|null */
    private ?array $googleTokenInfo = null;

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options): ResponseInterface
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

        if (str_contains($url, self::GOOGLE_HOST) && null !== $this->googleTokenInfo) {
            return new MockResponse(json_encode($this->googleTokenInfo, \JSON_THROW_ON_ERROR), ['http_code' => 200]);
        }

        return array_shift($this->queue) ?? throw new \LogicException(\sprintf('Unexpected HTTP request: %s %s', $method, $url));
    }

    public function queue(MockResponse ...$responses): void
    {
        array_push($this->queue, ...$responses);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setGoogleTokenInfo(array $payload): void
    {
        $this->googleTokenInfo = $payload;
    }

    public function useQueueForGoogle(): void
    {
        $this->googleTokenInfo = null;
    }

    /**
     * @return list<array{method: string, url: string, options: array<string, mixed>}>
     */
    public function backendRequests(): array
    {
        return array_values(array_filter(
            $this->requests,
            static fn (array $request): bool => !str_contains($request['url'], self::GOOGLE_HOST),
        ));
    }

    /**
     * @param array{options: array<string, mixed>} $request
     */
    public static function header(array $request, string $name): ?string
    {
        /** @var array<string, list<string>> $headers */
        $headers = $request['options']['normalized_headers'] ?? [];
        $line = $headers[strtolower($name)][0] ?? null;

        return null === $line ? null : substr($line, \strlen($name) + 2);
    }
}
