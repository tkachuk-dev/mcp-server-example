<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Backend response fixtures (tests/Fixtures/{Users,Billing}), must match the backends' public/openapi/internal-ai.yaml (see tests/Contract).
 */
final class Fixtures
{
    public const string USERS = 'Users';
    public const string BILLING = 'Billing';

    public static function path(string $service, string $name): string
    {
        return \sprintf('%s/../Fixtures/%s/%s.json', __DIR__, $service, $name);
    }

    public static function raw(string $service, string $name): string
    {
        return (string) file_get_contents(self::path($service, $name));
    }

    /**
     * @return array<mixed>
     */
    public static function decode(string $service, string $name): array
    {
        /** @var array<mixed> $data */
        $data = json_decode(self::raw($service, $name), true, flags: \JSON_THROW_ON_ERROR);

        return $data;
    }

    public static function response(string $service, string $name, int $status = 200): MockResponse
    {
        return new MockResponse(self::raw($service, $name), [
            'http_code' => $status,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
    }

    public static function status(int $status): MockResponse
    {
        return new MockResponse('{"message":"internal details that must not leak"}', ['http_code' => $status]);
    }

    public static function networkError(): MockResponse
    {
        return new MockResponse('', ['error' => 'Connection refused']);
    }
}
