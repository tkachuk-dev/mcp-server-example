<?php

declare(strict_types=1);

namespace App\Controller\Health;

use App\Dto\Health\HealthResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class LiveController extends AbstractController
{
    #[Route('/health/live', name: 'health_live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return $this->json(HealthResponse::withMessage('OK'));
    }
}
