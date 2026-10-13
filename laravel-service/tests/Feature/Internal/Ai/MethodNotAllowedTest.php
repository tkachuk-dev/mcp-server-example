<?php

declare(strict_types=1);

namespace Tests\Feature\Internal\Ai;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithInternalAi;
use Tests\TestCase;

class MethodNotAllowedTest extends TestCase
{
    use InteractsWithInternalAi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInternalAi();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function methods(): iterable
    {
        yield 'POST' => ['POST'];
        yield 'PUT' => ['PUT'];
        yield 'PATCH' => ['PATCH'];
        yield 'DELETE' => ['DELETE'];
    }

    #[DataProvider('methods')]
    public function test_non_get_method_with_valid_token_returns_405(string $method): void
    {
        $tenant = Tenant::factory()->create();

        $this->json($method, "/internal/ai/v1/tenants/{$tenant->id}/rfm-summary", [], $this->mcpHeaders())
            ->assertStatus(405)
            ->assertJsonStructure(['message']);
    }
}
