<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class GoogleTokenInfo
{
    private const string VERIFIED = 'true';

    public function __construct(
        public string $email,
        public string $audience,
        public bool $emailVerified,
        public int $expiresIn,
    ) {
    }

    /**
     * @param array<string, mixed> $payload tokeninfo response of oauth2.googleapis.com
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            email: \is_string($payload['email'] ?? null) ? $payload['email'] : '',
            audience: \is_string($payload['aud'] ?? null) ? $payload['aud'] : '',
            // Google returns the string "true", never a JSON boolean.
            emailVerified: self::VERIFIED === ($payload['email_verified'] ?? null),
            expiresIn: is_numeric($payload['expires_in'] ?? null) ? (int) $payload['expires_in'] : 0,
        );
    }
}
