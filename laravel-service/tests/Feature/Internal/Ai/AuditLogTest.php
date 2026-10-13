<?php

declare(strict_types=1);

namespace Tests\Feature\Internal\Ai;

use App\Http\Middleware\LogInternalAiRequest;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Logger as IlluminateLogger;
use Illuminate\Support\Facades\Log;
use Mockery;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Tests\Support\InteractsWithInternalAi;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use InteractsWithInternalAi;
    use RefreshDatabase;

    public function test_every_request_is_written_to_the_audit_channel(): void
    {
        $this->setUpInternalAi();
        $tenant = Tenant::factory()->create();
        $audit = Mockery::spy(LoggerInterface::class);
        Log::shouldReceive('channel')->with(LogInternalAiRequest::LOG_CHANNEL)->andReturn($audit);

        $this->mcpGet("/internal/ai/v1/tenants/{$tenant->id}/churn-risk?limit=5")->assertOk();

        $audit->shouldHaveReceived('info')->once()->with('internal_ai_request', Mockery::on(
            fn (array $context): bool => $context['actor'] === 'mcp:'.self::ACTING_USER
                && $context['path'] === "internal/ai/v1/tenants/{$tenant->id}/churn-risk"
                && $context['query'] === ['limit' => '5']
                && $context['status'] === 200
                && is_float($context['duration_ms']),
        ));
    }

    public function test_audit_channel_is_named_ai_audit(): void
    {
        $channel = Log::channel(LogInternalAiRequest::LOG_CHANNEL);
        $this->assertInstanceOf(IlluminateLogger::class, $channel);

        $logger = $channel->getLogger();
        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertSame('ai_audit', $logger->getName());
    }

    public function test_unauthenticated_request_is_logged_as_anonymous(): void
    {
        $audit = Mockery::spy(LoggerInterface::class);
        Log::shouldReceive('channel')->with(LogInternalAiRequest::LOG_CHANNEL)->andReturn($audit);

        $this->getJson('/internal/ai/v1/tenants/1/rfm-summary')->assertUnauthorized();

        $audit->shouldHaveReceived('info')->once()->with('internal_ai_request', Mockery::on(
            fn (array $context): bool => $context['actor'] === 'anonymous' && $context['status'] === 401,
        ));
    }
}
