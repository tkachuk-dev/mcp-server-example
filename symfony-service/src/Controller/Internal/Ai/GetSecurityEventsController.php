<?php

declare(strict_types=1);

namespace App\Controller\Internal\Ai;

use App\Dto\Ai\Request\SecurityEventsQuery;
use App\Service\Ai\SecurityEventsProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

class GetSecurityEventsController extends AbstractController
{
    public function __construct(private readonly SecurityEventsProvider $provider)
    {
    }

    // Symfony 7.4 passes the raw string to an int argument (TypeError → 500), so a digits requirement is needed for a 404.
    #[Route('/internal/ai/v1/tenants/{tenantId}/security-events', requirements: ['tenantId' => Requirement::DIGITS], name: 'internal_ai_get_security_events', methods: ['GET'])]
    public function getSecurityEvents(
        int $tenantId,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        SecurityEventsQuery $query = new SecurityEventsQuery(),
    ): JsonResponse {
        return $this->json($this->provider->getEvents($tenantId, $query));
    }
}
