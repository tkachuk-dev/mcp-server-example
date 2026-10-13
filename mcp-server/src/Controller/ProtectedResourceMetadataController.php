<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\ProtectedResourceMetadata;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ProtectedResourceMetadataController
{
    public function __construct(
        private ProtectedResourceMetadata $metadata,
    ) {
    }

    // Also answers the path-suffixed form (RFC 9728 §3.1, e.g. /.well-known/oauth-protected-resource/mcp) some clients probe.
    #[Route(
        path: ProtectedResourceMetadata::PATH.'{suffix}',
        name: 'oauth_protected_resource',
        requirements: ['suffix' => '(/.*)?'],
        defaults: ['suffix' => ''],
        methods: ['GET'],
    )]
    public function getMetadata(): JsonResponse
    {
        return new JsonResponse($this->metadata->toArray());
    }
}
