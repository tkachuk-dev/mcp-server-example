<?php

declare(strict_types=1);

namespace Tests\Feature\Internal\Ai;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\UserProjection;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AssertsOpenApi;
use Tests\Support\InteractsWithInternalAi;
use Tests\TestCase;

class GetRevenueSummaryTest extends TestCase
{
    use AssertsOpenApi;
    use InteractsWithInternalAi;
    use RefreshDatabase;

    private const string PATH = '/internal/ai/v1/tenants/{tenantId}/revenue-summary';

    private Tenant $tenant;

    private UserProjection $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInternalAi();
        $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
        $this->tenant = Tenant::factory()->create(['currency' => 'USD']);
        $this->customer = UserProjection::factory()->for($this->tenant)->create();
    }

    public function test_returns_daily_revenue_with_refunds_and_subscriptions(): void
    {
        $this->paidOrder('2026-10-13 10:00:00', 10_000);
        $this->paidOrder('2026-10-13 11:00:00', 5_000);
        $refunded = $this->paidOrder('2026-10-10 09:00:00', 3_000);
        $this->paidOrder('2026-10-01 09:00:00', 99_000);
        Order::factory()->create(['user_id' => $this->customer->user_id, 'amount_minor' => 7_000]);
        Refund::factory()->forPayment($refunded)->create(['amount_minor' => 2_000, 'refunded_at' => '2026-10-12 08:00:00']);
        Subscription::factory()->create(['user_id' => $this->customer->user_id, 'started_at' => '2026-10-11 08:00:00']);
        Subscription::factory()->cancelled(CarbonImmutable::parse('2026-10-12 08:00:00'))
            ->create(['user_id' => $this->customer->user_id, 'started_at' => '2026-09-01 08:00:00']);

        $response = $this->mcpGet($this->uri('?days=7'));

        $response->assertOk()
            ->assertJsonStructure([
                'tenant_id', 'days', 'currency', 'period_start', 'period_end', 'generated_at',
                'daily' => [['date', 'revenue_minor', 'orders_count', 'avg_order_value_minor', 'refunds_minor', 'refunds_count', 'new_subscriptions', 'cancelled_subscriptions']],
            ])
            ->assertJsonPath('days', 7)
            ->assertJsonPath('currency', 'USD')
            ->assertJsonPath('period_start', '2026-10-07')
            ->assertJsonPath('period_end', '2026-10-13')
            ->assertJsonCount(7, 'daily')
            ->assertJsonPath('daily.0.date', '2026-10-07')
            ->assertJsonPath('daily.0.revenue_minor', 0)
            ->assertJsonPath('daily.3.revenue_minor', 3_000)
            ->assertJsonPath('daily.4.new_subscriptions', 1)
            ->assertJsonPath('daily.5', [
                'date' => '2026-10-12', 'revenue_minor' => 0, 'orders_count' => 0, 'avg_order_value_minor' => 0,
                'refunds_minor' => 2_000, 'refunds_count' => 1, 'new_subscriptions' => 0, 'cancelled_subscriptions' => 1,
            ])
            ->assertJsonPath('daily.6.revenue_minor', 15_000)
            ->assertJsonPath('daily.6.orders_count', 2)
            ->assertJsonPath('daily.6.avg_order_value_minor', 7_500);
        $this->assertMatchesOpenApi($response, self::PATH);
    }

    public function test_defaults_to_30_days(): void
    {
        $this->mcpGet($this->uri())->assertOk()->assertJsonPath('days', 30)->assertJsonCount(30, 'daily');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDays(): iterable
    {
        yield 'zero' => ['0'];
        yield 'too big' => ['1000'];
        yield 'not a number' => ['abc'];
    }

    #[DataProvider('invalidDays')]
    public function test_invalid_days_returns_422(string $days): void
    {
        $response = $this->mcpGet($this->uri("?days={$days}"));

        $response->assertUnprocessable()->assertJsonValidationErrors(['days']);
        $this->assertMatchesOpenApi($response, self::PATH);
    }

    private function paidOrder(string $paidAt, int $amount): Payment
    {
        $order = Order::factory()
            ->paid(CarbonImmutable::parse($paidAt, 'UTC'))
            ->create(['user_id' => $this->customer->user_id, 'amount_minor' => $amount]);

        return Payment::factory()->forOrder($order)->create();
    }

    private function uri(string $query = ''): string
    {
        return "/internal/ai/v1/tenants/{$this->tenant->id}/revenue-summary{$query}";
    }
}
