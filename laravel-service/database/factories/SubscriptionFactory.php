<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\UserProjection;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'user_id' => UserProjection::factory(),
            'tenant_id' => fn (array $attributes): int => UserProjection::query()
                ->findOrFail((int) $attributes['user_id'])->tenant_id,
            'plan' => fake()->randomElement(['basic', 'pro', 'business']),
            'amount_minor' => fake()->randomElement([9_900, 29_900, 99_900]),
            'status' => SubscriptionStatus::Active,
            'started_at' => now(),
            'cancelled_at' => null,
        ];
    }

    public function cancelled(?DateTimeInterface $cancelledAt = null): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => $cancelledAt ?? now(),
        ]);
    }
}
