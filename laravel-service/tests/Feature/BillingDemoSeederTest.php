<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\UserProjection;
use Database\Seeders\BillingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_twice_replaces_the_demo_data(): void
    {
        $this->seed(BillingDemoSeeder::class);
        $this->seed(BillingDemoSeeder::class);

        $this->assertSame([1, 2], Tenant::query()->orderBy('id')->pluck('id')->all());
        $this->assertSame(100, UserProjection::query()->count());
    }
}
