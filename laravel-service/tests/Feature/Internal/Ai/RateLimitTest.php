<?php

declare(strict_types=1);

namespace Tests\Feature\Internal\Ai;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssertsOpenApi;
use Tests\Support\InteractsWithInternalAi;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use AssertsOpenApi;
    use InteractsWithInternalAi;
    use RefreshDatabase;

    public function test_exceeding_the_limit_returns_429_per_actor(): void
    {
        $this->setUpInternalAi();
        config(['internal_ai.rate_limit_per_minute' => 2]);
        $uri = '/internal/ai/v1/tenants/'.Tenant::factory()->create()->id.'/rfm-summary';

        $this->mcpGet($uri)->assertOk();
        $this->mcpGet($uri)->assertOk();
        $response = $this->mcpGet($uri);

        $response->assertTooManyRequests()->assertJsonStructure(['message']);
        $this->assertMatchesOpenApi($response, '/internal/ai/v1/tenants/{tenantId}/rfm-summary');
        $this->getJson($uri, $this->mcpHeaders(actingUser: 'other@example.com'))->assertOk();
    }
}
