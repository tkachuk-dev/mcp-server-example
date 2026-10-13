<?php

declare(strict_types=1);

namespace Tests\Feature\Internal\Ai;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\UserProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AssertsNoPii;
use Tests\Support\InteractsWithInternalAi;
use Tests\TestCase;

class NoPiiTest extends TestCase
{
    use AssertsNoPii;
    use InteractsWithInternalAi;
    use RefreshDatabase;

    /**
     * @return iterable<string, array{string}>
     */
    public static function endpoints(): iterable
    {
        yield 'rfm-summary' => ['rfm-summary'];
        yield 'churn-risk' => ['churn-risk'];
        yield 'revenue-summary' => ['revenue-summary'];
    }

    #[DataProvider('endpoints')]
    public function test_response_contains_no_pii_keys(string $endpoint): void
    {
        $this->setUpInternalAi();
        $tenant = Tenant::factory()->create();
        $customer = UserProjection::factory()->for($tenant)->create();
        $order = Order::factory()->paid()->create(['user_id' => $customer->user_id]);
        Refund::factory()->forPayment(Payment::factory()->forOrder($order)->create())->create();
        Subscription::factory()->create(['user_id' => $customer->user_id]);

        $response = $this->mcpGet("/internal/ai/v1/tenants/{$tenant->id}/{$endpoint}")->assertOk();

        $this->assertNoPiiKeys((array) $response->json());
    }
}
