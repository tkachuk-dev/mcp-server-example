<?php

declare(strict_types=1);

namespace App\Tests\Functional\Internal\Ai;

use App\DataFixtures\AppFixtures;
use App\Tests\Support\InternalAiWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class AuthenticationTest extends InternalAiWebTestCase
{
    private const string URI = self::BASE_PATH.'/1/users-summary';

    public function testRequestWithoutTokenIsUnauthorized(): void
    {
        $this->client->request('GET', self::URI, server: ['HTTP_X_ACTING_USER' => self::ACTING_USER]);

        $this->assertUnauthorized();
    }

    public function testRequestWithInvalidTokenIsUnauthorized(): void
    {
        $this->mcpGet(self::URI, token: 'not-a-valid-token');

        $this->assertUnauthorized();
    }

    #[DataProvider('invalidActingUsers')]
    public function testMissingOrInvalidActingUserIsUnauthorized(?string $actingUser): void
    {
        $server = ['HTTP_AUTHORIZATION' => 'Bearer '.self::SERVICE_TOKEN];
        if (null !== $actingUser) {
            $server['HTTP_X_ACTING_USER'] = $actingUser;
        }

        $this->client->request('GET', self::URI, server: $server);

        $this->assertUnauthorized();
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function invalidActingUsers(): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'not an email' => ['alice'];
        yield 'too long' => [str_repeat('a', 250).'@example.com'];
    }

    public function testRotatedTokenIsAccepted(): void
    {
        $response = $this->mcpGet(self::URI, token: self::ROTATED_SERVICE_TOKEN);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testUserSessionDoesNotGrantAccess(): void
    {
        // A real json_login (not loginUser(), which injects the token straight into token storage).
        $this->client->jsonRequest('POST', '/api/auth/login', ['username' => 'user1@acme.test', 'password' => AppFixtures::DEFAULT_PASSWORD]);
        self::assertSame(204, $this->client->getResponse()->getStatusCode());
        self::assertNotNull($this->client->getCookieJar()->get('MOCKSESSID'));

        $this->client->request('GET', self::URI);

        $this->assertUnauthorized();
    }

    public function testUserCredentialsAsBearerTokenAreRejected(): void
    {
        $this->client->request('GET', self::URI, server: [
            'HTTP_AUTHORIZATION' => 'Bearer user1@acme.test:password',
            'HTTP_X_ACTING_USER' => self::ACTING_USER,
        ]);

        $this->assertUnauthorized();
    }

    #[DataProvider('writeMethods')]
    public function testNonGetMethodIsNotAllowed(string $method): void
    {
        $this->client->request($method, self::URI, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.self::SERVICE_TOKEN,
            'HTTP_X_ACTING_USER' => self::ACTING_USER,
        ]);

        $response = $this->client->getResponse();
        self::assertSame(405, $response->getStatusCode());
        self::assertSame(['message' => 'Method Not Allowed'], $this->json($response));
        $this->assertMatchesSchema('Error', $response);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function writeMethods(): iterable
    {
        yield 'POST' => ['POST'];
        yield 'PUT' => ['PUT'];
        yield 'DELETE' => ['DELETE'];
    }

    private function assertUnauthorized(): void
    {
        $response = $this->client->getResponse();
        self::assertSame(401, $response->getStatusCode());
        self::assertSame(['message' => 'Unauthorized'], $this->json($response));
        $this->assertMatchesSchema('Error', $response);
    }
}
