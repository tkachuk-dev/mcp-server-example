<?php

declare(strict_types=1);

namespace App\Controller\Internal\Ai;

use App\Service\Ai\UsersSummaryProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

class GetUsersSummaryController extends AbstractController
{
    public function __construct(private readonly UsersSummaryProvider $provider)
    {
    }

    // Symfony 7.4 passes the raw string to an int argument (TypeError → 500), so a digits requirement is needed for a 404.
    #[Route('/internal/ai/v1/tenants/{tenantId}/users-summary', requirements: ['tenantId' => Requirement::DIGITS], name: 'internal_ai_get_users_summary', methods: ['GET'])]
    public function getUsersSummary(int $tenantId): JsonResponse
    {
        return $this->json($this->provider->getSummary($tenantId));
    }
}
