<?php

declare(strict_types=1);

namespace Tests\Feature\Internal\Ai;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\UserProjection;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AssertsOpenApi;
use Tests\Support\InteractsWithInternalAi;
use Tests\TestCase;

class GetChurnRiskTest extends TestCase
{
    use AssertsOpenApi;
    use InteractsWithInternalAi;
    use RefreshDatabase;

    private const string PATH = '/internal/ai/v1/tenants/{tenantId}/churn-risk';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInternalAi();
        $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
        $this->tenant = Tenant::factory()->create();
    }

    public function test_returns_active_customers_sorted_by_score(): void
    {
        $noOrders = UserProjection::factory()->for($this->tenant)->create();
        $dormant = $this->customerWithOrders(ordersCount: 1, daysAgo: 90);
        $loyal = $this->customerWithOrders(ordersCount: 5, daysAgo: 1);
        $this->customerWithOrders(ordersCount: 1, daysAgo: 300, state: 'blocked');
        $this->customerWithOrders(ordersCount: 1, daysAgo: 300, state: 'deleted');

        $response = $this->mcpGet($this->uri());

        $response->assertOk()
            ->assertJsonStructure(['tenant_id', 'limit', 'generated_at', 'customers' => [['user_id', 'churn_score', 'segment', 'last_order_at']]])
            ->assertJsonPath('limit', 50)
            ->assertJsonCount(3, 'customers')
            ->assertJsonPath('customers.0', ['user_id' => $noOrders->user_id, 'churn_score' => 1, 'segment' => 'lost', 'last_order_at' => null])
            ->assertJsonPath('customers.1.user_id', $dormant->user_id)
            ->assertJsonPath('customers.1.churn_score', 0.62)
            ->assertJsonPath('customers.1.segment', 'at_risk')
            ->assertJsonPath('customers.1.last_order_at', '2026-07-15T12:00:00+00:00')
            ->assertJsonPath('customers.2.user_id', $loyal->user_id)
            ->assertJsonPath('customers.2.churn_score', 0.15)
            ->assertJsonPath('customers.2.segment', 'champions');
        $this->assertMatchesOpenApi($response, self::PATH);
    }

    public function test_limit_restricts_number_of_customers(): void
    {
        UserProjection::factory()->for($this->tenant)->count(3)->create();

        $this->mcpGet($this->uri('?limit=2'))->assertOk()->assertJsonPath('limit', 2)->assertJsonCount(2, 'customers');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLimits(): iterable
    {
        yield 'zero' => ['0'];
        yield 'too big' => ['1000'];
        yield 'not a number' => ['abc'];
    }

    #[DataProvider('invalidLimits')]
    public function test_invalid_limit_returns_422(string $limit): void
    {
        $response = $this->mcpGet($this->uri("?limit={$limit}"));

        $response->assertUnprocessable()->assertJsonValidationErrors(['limit']);
        $this->assertMatchesOpenApi($response, self::PATH);
    }

    private function customerWithOrders(int $ordersCount, int $daysAgo, ?string $state = null): UserProjection
    {
        $factory = UserProjection::factory()->for($this->tenant);
        $customer = ($state === null ? $factory : $factory->{$state}())->create();

        Order::factory()->count($ordersCount)->paid(now()->subDays($daysAgo))->create(['user_id' => $customer->user_id]);

        return $customer;
    }

    private function uri(string $query = ''): string
    {
        return "/internal/ai/v1/tenants/{$this->tenant->id}/churn-risk{$query}";
    }
}
