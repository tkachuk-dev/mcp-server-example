<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\UserProjection;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => UserProjection::factory(),
            'tenant_id' => fn (array $attributes): int => UserProjection::query()
                ->findOrFail((int) $attributes['user_id'])->tenant_id,
            'status' => OrderStatus::Pending,
            'amount_minor' => fake()->numberBetween(1_000, 500_000),
            'paid_at' => null,
        ];
    }

    public function paid(?DateTimeInterface $paidAt = null): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Paid,
            'paid_at' => $paidAt ?? now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => OrderStatus::Cancelled]);
    }
}
