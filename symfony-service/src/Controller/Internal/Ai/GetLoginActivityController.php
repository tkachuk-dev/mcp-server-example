<?php

declare(strict_types=1);

namespace App\Controller\Internal\Ai;

use App\Dto\Ai\Request\LoginActivityQuery;
use App\Service\Ai\LoginActivityProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

class GetLoginActivityController extends AbstractController
{
    public function __construct(private readonly LoginActivityProvider $provider)
    {
    }

    // Symfony 7.4 passes the raw string to an int argument (TypeError → 500), so a digits requirement is needed for a 404.
    #[Route('/internal/ai/v1/tenants/{tenantId}/login-activity', requirements: ['tenantId' => Requirement::DIGITS], name: 'internal_ai_get_login_activity', methods: ['GET'])]
    public function getLoginActivity(
        int $tenantId,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        LoginActivityQuery $query = new LoginActivityQuery(),
    ): JsonResponse {
        return $this->json($this->provider->getActivity($tenantId, $query));
    }
}
