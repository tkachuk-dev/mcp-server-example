<?php

declare(strict_types=1);

namespace App\Tests\Functional\Internal\Ai;

use App\Tests\Support\InternalAiWebTestCase;

final class UsersSummaryTest extends InternalAiWebTestCase
{
    public function testReturnsTenantAggregates(): void
    {
        $response = $this->mcpGet(self::BASE_PATH.'/1/users-summary');

        self::assertSame(200, $response->getStatusCode());
        $this->assertMatchesSchema('UsersSummary', $response);

        $body = $this->json($response);
        self::assertSame(self::TENANT_ID, $body['tenant_id']);
        self::assertSame(10, $body['total_users']);
        self::assertSame(6, $body['active_last_30_days']);
        self::assertSame(1, $body['blocked_users']);
        self::assertSame(2, $body['unverified_email_users']);
        self::assertEquals(0.4, $body['two_factor_enabled_ratio']);
        self::assertSame([
            ['role' => 1, 'role_name' => 'ROLE_USER', 'users_count' => 7],
            ['role' => 2, 'role_name' => 'ROLE_MANAGER', 'users_count' => 2],
            ['role' => 3, 'role_name' => 'ROLE_ADMIN', 'users_count' => 1],
        ], $body['roles']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $body['generated_at']);
    }

    public function testDataIsIsolatedPerTenant(): void
    {
        $body = $this->json($this->mcpGet(self::BASE_PATH.'/2/users-summary'));

        self::assertSame(self::OTHER_TENANT_ID, $body['tenant_id']);
        self::assertSame(1, $body['total_users']);
    }

    public function testUnknownTenantIsNotFound(): void
    {
        $response = $this->mcpGet(self::BASE_PATH.'/999/users-summary');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['message' => 'Tenant not found'], $this->json($response));
        $this->assertMatchesSchema('Error', $response);
    }

    public function testNonNumericTenantIsNotFound(): void
    {
        self::assertSame(404, $this->mcpGet(self::BASE_PATH.'/abc/users-summary')->getStatusCode());
    }
}
