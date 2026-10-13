<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Health\ReadinessChecker;
use Mockery\MockInterface;
use Tests\Support\AssertsOpenApi;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use AssertsOpenApi;

    public function test_liveness_is_public(): void
    {
        $response = $this->getJson('/health/live');

        $response->assertOk()->assertJsonPath('service', 'billing-service');
        $this->assertMatchesOpenApi($response, '/health/live');
    }

    public function test_readiness_is_ok_when_dependencies_are_available(): void
    {
        $response = $this->getJson('/health/ready');

        $response->assertOk();
        $this->assertMatchesOpenApi($response, '/health/ready');
    }

    public function test_readiness_failure_exposes_no_details(): void
    {
        $this->mock(ReadinessChecker::class, fn (MockInterface $mock) => $mock->shouldReceive('isReady')->andReturnFalse());

        $response = $this->getJson('/health/ready');

        $response->assertServiceUnavailable()->assertExactJson(['message' => 'Service unavailable']);
        $this->assertMatchesOpenApi($response, '/health/ready');
    }
}
