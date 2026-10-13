<?php

declare(strict_types=1);

namespace App\Tests\Contract;

use App\Tests\Support\Fixtures;
use App\Tests\Support\OpenApiDocument;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Backend fixtures must match the owners' OpenAPI specs. Snapshots of the specs live in
 * tests/Contract/openapi/{users,billing}/ and are refreshed with `composer contract:sync`.
 */
final class FixturesMatchOpenApiTest extends TestCase
{
    private const string SPEC_DIR = __DIR__.'/openapi';

    /** @var array<string, OpenApiDocument> */
    private static array $documents = [];

    /**
     * @return iterable<string, array{string, string, string, string, int}>
     */
    public static function fixtures(): iterable
    {
        yield 'users summary 200' => ['users', Fixtures::USERS, 'users-summary', '/users-summary', 200];
        yield 'users login activity 200' => ['users', Fixtures::USERS, 'login-activity', '/login-activity', 200];
        yield 'users security events 200' => ['users', Fixtures::USERS, 'security-events', '/security-events', 200];
        yield 'users 404' => ['users', Fixtures::USERS, 'error-404', '/users-summary', 404];
        yield 'users 422' => ['users', Fixtures::USERS, 'error-422', '/login-activity', 422];
        yield 'billing rfm 200' => ['billing', Fixtures::BILLING, 'rfm-summary', '/rfm-summary', 200];
        yield 'billing churn 200' => ['billing', Fixtures::BILLING, 'churn-risk', '/churn-risk', 200];
        yield 'billing revenue 200' => ['billing', Fixtures::BILLING, 'revenue-summary', '/revenue-summary', 200];
        yield 'billing 404' => ['billing', Fixtures::BILLING, 'error-404', '/rfm-summary', 404];
        yield 'billing 422' => ['billing', Fixtures::BILLING, 'error-422', '/churn-risk', 422];
    }

    #[DataProvider('fixtures')]
    public function testFixtureMatchesSpec(string $spec, string $fixtureService, string $fixture, string $pathSuffix, int $status): void
    {
        $entry = \sprintf('%s/%s/openapi.yaml', self::SPEC_DIR, $spec);
        if (!is_file($entry)) {
            self::markTestSkipped(\sprintf('No %s spec snapshot; run `composer contract:sync`.', $spec));
        }

        $schema = (self::$documents[$entry] ??= new OpenApiDocument($entry))->responseSchema($pathSuffix, $status);
        $data = json_decode(Fixtures::raw($fixtureService, $fixture), flags: \JSON_THROW_ON_ERROR);

        $result = (new Validator())->validate($data, $schema);

        self::assertTrue(
            $result->isValid(),
            \sprintf('%s/%s.json does not match %s %d: %s', $fixtureService, $fixture, $pathSuffix, $status, json_encode(
                null === $result->error() ? [] : (new ErrorFormatter())->format($result->error()),
                \JSON_UNESCAPED_UNICODE,
            )),
        );
    }
}
