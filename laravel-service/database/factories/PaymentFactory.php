<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->paid(),
            'tenant_id' => fn (array $attributes): int => Order::query()->findOrFail((int) $attributes['order_id'])->tenant_id,
            'amount_minor' => fn (array $attributes): int => Order::query()->findOrFail((int) $attributes['order_id'])->amount_minor,
            'status' => PaymentStatus::Succeeded,
            'paid_at' => now(),
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state([
            'order_id' => $order->id,
            'tenant_id' => $order->tenant_id,
            'amount_minor' => $order->amount_minor,
            'paid_at' => $order->paid_at,
        ]);
    }
}
