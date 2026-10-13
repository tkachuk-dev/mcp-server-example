<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'order_id' => fn (array $attributes): int => Payment::query()->findOrFail((int) $attributes['payment_id'])->order_id,
            'tenant_id' => fn (array $attributes): int => Payment::query()->findOrFail((int) $attributes['payment_id'])->tenant_id,
            'amount_minor' => fake()->numberBetween(100, 1_000),
            'refunded_at' => now(),
        ];
    }

    public function forPayment(Payment $payment): static
    {
        return $this->state([
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'tenant_id' => $payment->tenant_id,
        ]);
    }
}
