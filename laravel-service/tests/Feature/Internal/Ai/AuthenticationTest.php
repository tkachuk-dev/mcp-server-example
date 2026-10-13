<?php

declare(strict_types=1);

namespace Tests\Feature\Internal\Ai;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AssertsOpenApi;
use Tests\Support\InteractsWithInternalAi;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use AssertsOpenApi;
    use InteractsWithInternalAi;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInternalAi();
        $this->tenant = Tenant::factory()->create();
    }

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
    public function test_request_without_token_is_unauthorized(string $endpoint): void
    {
        $response = $this->getJson($this->uri($endpoint), ['X-Acting-User' => self::ACTING_USER]);

        $response->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
        $this->assertMatchesOpenApi($response, '/internal/ai/v1/tenants/{tenantId}/'.$endpoint);
    }

    #[DataProvider('endpoints')]
    public function test_request_with_invalid_token_is_unauthorized(string $endpoint): void
    {
        $this->getJson($this->uri($endpoint), $this->mcpHeaders(token: 'wrong-token'))->assertUnauthorized();
    }

    public function test_request_without_acting_user_is_unauthorized(): void
    {
        $this->getJson($this->uri('rfm-summary'), ['Authorization' => 'Bearer '.self::SERVICE_TOKEN])->assertUnauthorized();
    }

    public function test_request_with_invalid_acting_user_is_unauthorized(): void
    {
        $this->getJson($this->uri('rfm-summary'), $this->mcpHeaders(actingUser: 'not-an-email'))->assertUnauthorized();
    }

    public function test_any_of_the_configured_hashes_is_accepted_for_rotation(): void
    {
        config(['internal_ai.token_hashes' => [hash('sha256', 'old-token'), hash('sha256', 'new-token')]]);

        $this->getJson($this->uri('rfm-summary'), $this->mcpHeaders(token: 'old-token'))->assertOk();
        $this->getJson($this->uri('rfm-summary'), $this->mcpHeaders(token: 'new-token'))->assertOk();
    }

    public function test_unauthenticated_request_does_not_reveal_whether_tenant_exists(): void
    {
        $this->getJson('/internal/ai/v1/tenants/999999/rfm-summary', ['X-Acting-User' => self::ACTING_USER])->assertUnauthorized();
    }

    public function test_access_does_not_authenticate_the_application_guard(): void
    {
        $this->mcpGet($this->uri('rfm-summary'))->assertOk();

        $this->assertGuest();
    }

    private function uri(string $endpoint): string
    {
        return "/internal/ai/v1/tenants/{$this->tenant->id}/{$endpoint}";
    }
}
