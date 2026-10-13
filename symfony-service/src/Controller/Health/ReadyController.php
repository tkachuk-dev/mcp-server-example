<?php

declare(strict_types=1);

namespace App\Controller\Health;

use App\Dto\Health\HealthResponse;
use App\Exception\ServiceNotReadyException;
use App\Service\Health\ReadinessChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ReadyController extends AbstractController
{
    public function __construct(private readonly ReadinessChecker $checker)
    {
    }

    #[Route('/health/ready', name: 'health_ready', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        if (!$this->checker->isReady()) {
            throw new ServiceNotReadyException('Service unavailable');
        }

        return $this->json(HealthResponse::withMessage('OK'));
    }
}
