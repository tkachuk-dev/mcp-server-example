<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\GoogleTokenHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class GoogleTokenHandlerTest extends TestCase
{
    private const string CLIENT_ID = 'our-client.apps.googleusercontent.com';
    private const string DOMAIN = 'example.com';
    private const string TOKEN = 'ya29.test-access-token';
    private const string GOOGLE_BASE_URI = 'https://oauth2.googleapis.com';

    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
    }

    public function testValidTokenYieldsInMemoryUserWithRoleUser(): void
    {
        $client = new MockHttpClient([$this->tokenInfo()], self::GOOGLE_BASE_URI);

        $badge = $this->handler($client)->getUserBadgeFrom(self::TOKEN);
        $user = $badge->getUser();

        self::assertSame('alice@example.com', $badge->getUserIdentifier());
        self::assertInstanceOf(InMemoryUser::class, $user);
        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testTokenIsSentInPostBodyNotInUrl(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://oauth2.googleapis.com/tokeninfo', $url);
            self::assertStringNotContainsString(self::TOKEN, $url);
            self::assertStringContainsString('access_token='.urlencode(self::TOKEN), (string) $options['body']);

            return $this->tokenInfo();
        }, self::GOOGLE_BASE_URI);

        $this->handler($client)->getUserBadgeFrom(self::TOKEN);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidTokenInfo(): iterable
    {
        yield 'audience of another app' => [['aud' => 'someone-else.apps.googleusercontent.com']];
        yield 'foreign domain' => [['email' => 'mallory@evil.com']];
        yield 'domain suffix trick' => [['email' => 'mallory@notexample.com']];
        yield 'email not verified' => [['email_verified' => 'false']];
        yield 'email_verified missing' => [['email_verified' => '']];
    }

    /**
     * @param array<string, string> $override
     */
    #[DataProvider('invalidTokenInfo')]
    public function testRejectsInvalidToken(array $override): void
    {
        $client = new MockHttpClient([$this->tokenInfo($override)], self::GOOGLE_BASE_URI);

        $this->expectException(BadCredentialsException::class);
        $this->handler($client)->getUserBadgeFrom(self::TOKEN);
    }

    public function testRejectsTokenGoogleDoesNotKnow(): void
    {
        $client = new MockHttpClient([new MockResponse('{"error_description":"Invalid Value"}', ['http_code' => 400])], self::GOOGLE_BASE_URI);

        $this->expectException(BadCredentialsException::class);
        $this->handler($client)->getUserBadgeFrom(self::TOKEN);
    }

    public function testGoogleOutageIsAnAuthenticationServiceError(): void
    {
        $client = new MockHttpClient([new MockResponse('', ['error' => 'Connection timed out'])], self::GOOGLE_BASE_URI);

        $this->expectException(AuthenticationServiceException::class);
        $this->handler($client)->getUserBadgeFrom(self::TOKEN);
    }

    public function testCachesValidResultBySha256OfToken(): void
    {
        $client = new MockHttpClient([$this->tokenInfo()], self::GOOGLE_BASE_URI);
        $handler = $this->handler($client);

        $handler->getUserBadgeFrom(self::TOKEN);
        $handler->getUserBadgeFrom(self::TOKEN);

        self::assertSame(1, $client->getRequestsCount());
        $keys = array_keys($this->cache->getValues());
        self::assertCount(1, $keys);
        self::assertStringContainsString(hash('sha256', self::TOKEN), $keys[0]);
        self::assertStringNotContainsString(self::TOKEN, implode(',', $keys));
    }

    public function testDoesNotCacheRejectedTokens(): void
    {
        $client = new MockHttpClient([$this->tokenInfo(['aud' => 'other']), $this->tokenInfo(['aud' => 'other'])], self::GOOGLE_BASE_URI);
        $handler = $this->handler($client);

        foreach ([1, 2] as $ignored) {
            try {
                $handler->getUserBadgeFrom(self::TOKEN);
            } catch (AuthenticationException) {
            }
        }

        self::assertSame(2, $client->getRequestsCount());
    }

    private function handler(MockHttpClient $client): GoogleTokenHandler
    {
        return new GoogleTokenHandler($client, $this->cache, self::CLIENT_ID, self::DOMAIN, new NullLogger());
    }

    /**
     * @param array<string, string> $override
     */
    private function tokenInfo(array $override = []): MockResponse
    {
        $payload = array_merge([
            'azp' => self::CLIENT_ID,
            'aud' => self::CLIENT_ID,
            'sub' => '1234567890',
            'scope' => 'openid https://www.googleapis.com/auth/userinfo.email',
            'exp' => '1790000000',
            'expires_in' => '3599',
            'email' => 'alice@example.com',
            'email_verified' => 'true',
            'access_type' => 'online',
        ], $override);

        return new MockResponse(json_encode($payload, \JSON_THROW_ON_ERROR), ['http_code' => 200]);
    }
}
