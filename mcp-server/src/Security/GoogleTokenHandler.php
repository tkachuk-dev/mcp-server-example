<?php

declare(strict_types=1);

namespace App\Security;

use App\Dto\GoogleTokenInfo;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Validates a Google OAuth access token via the tokeninfo endpoint.
 */
final readonly class GoogleTokenHandler implements AccessTokenHandlerInterface
{
    public const string TOKENINFO_PATH = '/tokeninfo';
    public const string ROLE = 'ROLE_USER';

    private const string CACHE_KEY_PREFIX = 'google_token.';
    private const int CACHE_TTL_SECONDS = 300;

    public function __construct(
        #[Autowire(service: 'google.oauth')]
        private HttpClientInterface $googleClient,
        #[Autowire(service: 'cache.app')]
        private CacheInterface $cache,
        #[Autowire(env: 'GOOGLE_CLIENT_ID')]
        private string $clientId,
        #[Autowire(env: 'ALLOWED_EMAIL_DOMAIN')]
        private string $allowedEmailDomain,
        private LoggerInterface $logger,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        // Failed checks throw inside the callback, so only valid tokens end up in the cache.
        $email = $this->cache->get(
            self::CACHE_KEY_PREFIX.hash('sha256', $accessToken),
            function (ItemInterface $item) use ($accessToken): string {
                $info = $this->fetchTokenInfo($accessToken);
                $this->assertValid($info);
                $item->expiresAfter(max(1, min(self::CACHE_TTL_SECONDS, $info->expiresIn)));

                return $info->email;
            },
        );

        return new UserBadge($email, static fn (string $identifier): InMemoryUser => new InMemoryUser($identifier, null, [self::ROLE]));
    }

    private function fetchTokenInfo(#[\SensitiveParameter] string $accessToken): GoogleTokenInfo
    {
        try {
            // POST keeps the token out of URLs and access logs.
            $response = $this->googleClient->request('POST', self::TOKENINFO_PATH, [
                'body' => ['access_token' => $accessToken],
            ]);

            if (Response::HTTP_OK !== $response->getStatusCode()) {
                throw new BadCredentialsException('Invalid Google access token.');
            }

            return GoogleTokenInfo::fromArray($response->toArray());
        } catch (HttpClientException $e) {
            $this->logger->error('Google tokeninfo is unreachable', ['exception' => $e::class]);

            throw new AuthenticationServiceException('Token validation is temporarily unavailable.', 0, $e);
        }
    }

    private function assertValid(GoogleTokenInfo $info): void
    {
        // aud must be our own client: otherwise a token issued to any other Google app would be accepted.
        if ('' === $this->clientId || !hash_equals($this->clientId, $info->audience)) {
            throw new BadCredentialsException('Token was issued to another client.');
        }

        if (!$info->emailVerified) {
            throw new BadCredentialsException('Email is not verified.');
        }

        if (!$this->isAllowedDomain($info->email)) {
            throw new BadCredentialsException('Email domain is not allowed.');
        }
    }

    private function isAllowedDomain(string $email): bool
    {
        $domain = strtolower(trim($this->allowedEmailDomain));

        return '' !== $domain && str_ends_with(strtolower($email), '@'.$domain);
    }
}
