<?php

declare(strict_types=1);

namespace Tests\Feature\Internal\Ai;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AssertsOpenApi;
use Tests\Support\InteractsWithInternalAi;
use Tests\TestCase;

class TenantNotFoundTest extends TestCase
{
    use AssertsOpenApi;
    use InteractsWithInternalAi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInternalAi();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function cases(): iterable
    {
        foreach (['rfm-summary', 'churn-risk', 'revenue-summary'] as $endpoint) {
            yield "{$endpoint} missing id" => [$endpoint, '999999'];
            yield "{$endpoint} non-numeric id" => [$endpoint, 'abc'];
        }
    }

    #[DataProvider('cases')]
    public function test_unknown_tenant_returns_404(string $endpoint, string $tenantId): void
    {
        $response = $this->mcpGet("/internal/ai/v1/tenants/{$tenantId}/{$endpoint}");

        $response->assertNotFound()->assertExactJson(['message' => 'Tenant not found']);

        if (ctype_digit($tenantId)) {
            $this->assertMatchesOpenApi($response, '/internal/ai/v1/tenants/{tenantId}/'.$endpoint);
        }
    }
}
