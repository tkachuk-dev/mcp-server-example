<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use App\Exceptions\ServiceNotReadyException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Health\HealthResource;
use App\Services\Health\ReadinessChecker;
use Carbon\CarbonImmutable;

class GetReadinessController extends Controller
{
    public function __construct(private readonly ReadinessChecker $readiness) {}

    public function getReadiness(): HealthResource
    {
        if (! $this->readiness->isReady()) {
            throw new ServiceNotReadyException('Service unavailable');
        }

        return new HealthResource(CarbonImmutable::now());
    }
}
