<?php

declare(strict_types=1);

namespace App\Security\Internal;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class McpServiceTokenHandler implements AccessTokenHandlerInterface
{
    public const string ROLE_AI_READ = 'ROLE_AI_READ';
    public const string ACTING_USER_HEADER = 'X-Acting-User';
    public const string ACTOR_PREFIX = 'mcp:';
    private const int MAX_EMAIL_LENGTH = 254;

    /**
     * @param array<mixed> $tokenHashes sha256 hashes from env JSON; several entries allow token rotation
     */
    public function __construct(
        #[Autowire(env: 'json:MCP_SERVICE_TOKEN_HASHES')]
        private readonly array $tokenHashes,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        if (!$this->isKnownToken($accessToken)) {
            throw new BadCredentialsException('Invalid service token.');
        }

        $actor = self::ACTOR_PREFIX.$this->actingUserEmail();

        return new UserBadge($actor, static fn (string $id): InMemoryUser => new InMemoryUser($id, null, [self::ROLE_AI_READ]));
    }

    private function isKnownToken(string $token): bool
    {
        $hash = hash('sha256', $token);
        foreach ($this->tokenHashes as $expected) {
            if (\is_string($expected) && hash_equals(strtolower($expected), $hash)) {
                return true;
            }
        }

        return false;
    }

    private function actingUserEmail(): string
    {
        $email = (string) $this->requestStack->getCurrentRequest()?->headers->get(self::ACTING_USER_HEADER, '');

        if (\strlen($email) > self::MAX_EMAIL_LENGTH || false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new BadCredentialsException('Missing or invalid X-Acting-User header.');
        }

        return $email;
    }
}
