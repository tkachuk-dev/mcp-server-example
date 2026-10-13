<?php

declare(strict_types=1);

namespace App\Tests\Functional\Internal\Ai;

use App\Tests\Support\InternalAiWebTestCase;
use Monolog\Handler\TestHandler;
use Monolog\Logger;

final class AuditLogTest extends InternalAiWebTestCase
{
    public function testEachRequestIsAudited(): void
    {
        $this->mcpGet(self::BASE_PATH.'/1/login-activity?days=7', ['HTTP_X_REQUEST_ID' => 'audit-req-1']);

        $records = $this->auditHandler()->getRecords();
        self::assertCount(1, $records);

        $context = $records[0]->context;
        self::assertSame('mcp:'.self::ACTING_USER, $context['actor']);
        self::assertSame('/internal/ai/v1/tenants/1/login-activity', $context['path']);
        self::assertSame(['days' => '7'], $context['query']);
        self::assertSame(200, $context['status']);
        self::assertIsFloat($context['duration_ms']);
        self::assertSame('audit-req-1', $records[0]->extra['request_id']);
        self::assertArrayHasKey('trace_id', $records[0]->extra);
    }

    public function testRejectedRequestIsAuditedAsAnonymous(): void
    {
        $this->client->request('GET', self::BASE_PATH.'/1/users-summary');

        $records = $this->auditHandler()->getRecords();
        self::assertCount(1, $records);
        self::assertSame('anonymous', $records[0]->context['actor']);
        self::assertSame(401, $records[0]->context['status']);
    }

    private function auditHandler(): TestHandler
    {
        /** @var Logger $logger */
        $logger = $this->client->getContainer()->get('monolog.logger.ai_audit');

        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                return $handler;
            }
        }

        self::fail('The ai_audit channel has no test handler.');
    }
}
