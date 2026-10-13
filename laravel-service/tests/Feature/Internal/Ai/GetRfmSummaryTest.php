<?php

declare(strict_types=1);

namespace Tests\Feature\Internal\Ai;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\UserProjection;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssertsOpenApi;
use Tests\Support\InteractsWithInternalAi;
use Tests\TestCase;

class GetRfmSummaryTest extends TestCase
{
    use AssertsOpenApi;
    use InteractsWithInternalAi;
    use RefreshDatabase;

    private const string PATH = '/internal/ai/v1/tenants/{tenantId}/rfm-summary';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInternalAi();
        $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
    }

    public function test_returns_aggregates_per_segment(): void
    {
        $tenant = Tenant::factory()->create(['currency' => 'UAH']);
        $this->customerWithOrders($tenant, ordersCount: 6, daysAgo: 1, amount: 1_000);
        $this->customerWithOrders($tenant, ordersCount: 1, daysAgo: 100, amount: 4_000);
        $this->customerWithOrders($tenant, ordersCount: 1, daysAgo: 300, amount: 2_000, blocked: true);
        Order::factory()->create(['user_id' => UserProjection::factory()->for($tenant)->create()->user_id]);
        $this->customerWithOrders(Tenant::factory()->create(), ordersCount: 9, daysAgo: 1, amount: 9_000);

        $response = $this->mcpGet("/internal/ai/v1/tenants/{$tenant->id}/rfm-summary");

        $response->assertOk()
            ->assertJsonStructure([
                'tenant_id', 'currency', 'generated_at',
                'segments' => [['segment', 'customers_count', 'avg_recency_days', 'avg_frequency', 'avg_monetary_minor']],
            ])
            ->assertJsonPath('tenant_id', $tenant->id)
            ->assertJsonPath('currency', 'UAH')
            ->assertJsonPath('generated_at', '2026-10-13T12:00:00+00:00')
            ->assertJsonCount(6, 'segments')
            ->assertJsonPath('segments.0', [
                'segment' => 'champions', 'customers_count' => 1,
                'avg_recency_days' => 1, 'avg_frequency' => 6, 'avg_monetary_minor' => 6_000,
            ])
            ->assertJsonPath('segments.1.customers_count', 0)
            ->assertJsonPath('segments.3.segment', 'at_risk')
            ->assertJsonPath('segments.3.customers_count', 1)
            ->assertJsonPath('segments.5.segment', 'lost')
            ->assertJsonPath('segments.5.customers_count', 1);
        $response->assertJsonMissingPath('status');
        $response->assertJsonMissingPath('data');
        $this->assertMatchesOpenApi($response, self::PATH);
    }

    public function test_result_is_cached(): void
    {
        $tenant = Tenant::factory()->create();
        $this->mcpGet("/internal/ai/v1/tenants/{$tenant->id}/rfm-summary")->assertOk();

        $this->customerWithOrders($tenant, ordersCount: 6, daysAgo: 1, amount: 1_000);

        $this->mcpGet("/internal/ai/v1/tenants/{$tenant->id}/rfm-summary")->assertJsonPath('segments.0.customers_count', 0);
    }

    private function customerWithOrders(Tenant $tenant, int $ordersCount, int $daysAgo, int $amount, bool $blocked = false): void
    {
        $factory = UserProjection::factory()->for($tenant);
        $customer = ($blocked ? $factory->blocked() : $factory)->create();

        Order::factory()
            ->count($ordersCount)
            ->paid(now()->subDays($daysAgo))
            ->create(['user_id' => $customer->user_id, 'amount_minor' => $amount]);
    }
}
