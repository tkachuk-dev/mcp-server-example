<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Http\Resources\Health\HealthResource;
use Carbon\CarbonImmutable;

class GetLivenessController extends Controller
{
    public function getLiveness(): HealthResource
    {
        return new HealthResource(CarbonImmutable::now());
    }
}
