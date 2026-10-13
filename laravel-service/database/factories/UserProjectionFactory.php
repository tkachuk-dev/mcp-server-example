<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserProjectionStatus;
use App\Models\Tenant;
use App\Models\UserProjection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserProjection>
 */
class UserProjectionFactory extends Factory
{
    protected $model = UserProjection::class;

    public function definition(): array
    {
        return [
            'user_id' => fake()->unique()->numberBetween(1, 10_000_000),
            'tenant_id' => Tenant::factory(),
            'status' => UserProjectionStatus::Active,
            'last_event_at' => now(),
        ];
    }

    public function blocked(): static
    {
        return $this->state(['status' => UserProjectionStatus::Blocked]);
    }

    public function deleted(): static
    {
        return $this->state(['status' => UserProjectionStatus::Deleted]);
    }
}
