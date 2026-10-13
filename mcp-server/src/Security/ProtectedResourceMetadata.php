<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * OAuth 2.0 Protected Resource Metadata (RFC 9728) of this MCP server.
 */
final readonly class ProtectedResourceMetadata
{
    public const string PATH = '/.well-known/oauth-protected-resource';
    public const string AUTHORIZATION_SERVER = 'https://accounts.google.com';
    public const array SCOPES = ['openid', 'email'];
    public const array BEARER_METHODS = ['header'];

    public function __construct(
        #[Autowire(env: 'MCP_PUBLIC_URL')]
        private string $publicUrl,
    ) {
    }

    public function resource(): string
    {
        return $this->publicUrl;
    }

    public function metadataUrl(): string
    {
        $parts = parse_url($this->publicUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return \sprintf('%s://%s%s%s', $scheme, $host, $port, self::PATH);
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function toArray(): array
    {
        return [
            'resource' => $this->resource(),
            'authorization_servers' => [self::AUTHORIZATION_SERVER],
            'scopes_supported' => self::SCOPES,
            'bearer_methods_supported' => self::BEARER_METHODS,
        ];
    }
}
