<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\UserProjection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class BillingDemoSeeder extends Seeder
{
    private const int CUSTOMERS_PER_TENANT = 50;

    private const int HISTORY_DAYS = 365;

    /** @var list<class-string<Model>> children first, so foreign keys never block the cleanup */
    private const array DEMO_MODELS = [
        Refund::class,
        Payment::class,
        Order::class,
        Subscription::class,
        UserProjection::class,
        Tenant::class,
    ];

    public function run(): void
    {
        fake()->seed(20261013);

        $this->purgeDemoData();

        $this->seedTenant(Tenant::factory()->create(['name' => 'Demo UAH', 'currency' => 'UAH']), 1_000);
        $this->seedTenant(Tenant::factory()->create(['name' => 'Demo USD', 'currency' => 'USD']), 2_000);
    }

    // Demo user_ids are fixed (1000+, 2000+), so re-seeding on top of old data would hit the primary key.
    // Truncating also restarts the ids, which keeps the demo tenants at 1 and 2 like the users-service fixtures.
    private function purgeDemoData(): void
    {
        foreach (self::DEMO_MODELS as $model) {
            $model::query()->truncate();
        }
    }

    private function seedTenant(Tenant $tenant, int $firstUserId): void
    {
        for ($i = 0; $i < self::CUSTOMERS_PER_TENANT; $i++) {
            $customer = $this->makeCustomer($tenant, $firstUserId + $i, $i);
            $this->seedOrders($customer);
            $this->seedSubscription($customer);
        }
    }

    private function makeCustomer(Tenant $tenant, int $userId, int $index): UserProjection
    {
        $factory = UserProjection::factory()->for($tenant);

        if ($index % 17 === 0) {
            $factory = $factory->blocked();
        }

        return $factory->create(['user_id' => $userId]);
    }

    private function seedOrders(UserProjection $customer): void
    {
        $ordersCount = fake()->numberBetween(0, 12);

        for ($i = 0; $i < $ordersCount; $i++) {
            $order = Order::factory()
                ->paid(now()->subDays(fake()->numberBetween(0, self::HISTORY_DAYS))->subMinutes(fake()->numberBetween(0, 1_439)))
                ->create(['user_id' => $customer->user_id, 'tenant_id' => $customer->tenant_id]);

            $payment = Payment::factory()->forOrder($order)->create();

            if (fake()->boolean(8)) {
                Refund::factory()->forPayment($payment)->create(['refunded_at' => $order->paid_at?->addDays(2)]);
            }
        }
    }

    private function seedSubscription(UserProjection $customer): void
    {
        if (! fake()->boolean(40)) {
            return;
        }

        $factory = Subscription::factory();
        $startedAt = now()->subDays(fake()->numberBetween(0, self::HISTORY_DAYS));

        if (fake()->boolean(25)) {
            $factory = $factory->cancelled($startedAt->copy()->addDays(fake()->numberBetween(1, 60))->min(now()));
        }

        $factory->create([
            'user_id' => $customer->user_id,
            'tenant_id' => $customer->tenant_id,
            'started_at' => $startedAt,
        ]);
    }
}
