<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use App\Backend\InternalApiClient;
use App\Dto\ToolInvocation;
use App\Enums\BackendService;
use App\Mcp\ArgumentValidator;
use App\Mcp\ToolExecutor;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Tools backed by the Users service (Symfony): accounts, authentication, roles, 2FA, security.
 * Ranges mirror the Users internal API contract v1.
 */
final readonly class UsersTools
{
    public const string TOOL_GET_SUMMARY = 'users_get_summary';
    public const string TOOL_GET_LOGIN_ACTIVITY = 'users_get_login_activity';
    public const string TOOL_GET_SECURITY_EVENTS = 'users_get_security_events';

    public const int DEFAULT_DAYS = 30;
    public const int MIN_DAYS = 1;
    public const int MAX_DAYS = 90;
    public const int DEFAULT_LIMIT = 50;
    public const int MIN_LIMIT = 1;
    public const int MAX_LIMIT = 200;

    private const string PARAM_DAYS = 'days';
    private const string PARAM_LIMIT = 'limit';

    public function __construct(
        #[Autowire(service: 'app.backend.users')]
        private InternalApiClient $client,
        private ToolExecutor $executor,
        private ArgumentValidator $validator,
    ) {
    }

    /**
     * @param int $tenantId Tenant (organisation) ID, >= 1. The same tenantId is used by the billing_* tools.
     *
     * @return array<mixed>
     */
    #[McpTool(
        name: self::TOOL_GET_SUMMARY,
        title: 'Users: tenant user summary',
        description: <<<'TXT'
            Users service (accounts & security domain). Returns aggregate user statistics of one tenant:
            total_users, active_last_30_days, blocked_users, unverified_email_users,
            two_factor_enabled_ratio (0..1), and roles[] (role, role_name, users_count), plus generated_at.
            Use for questions about how many users/accounts a tenant has, who is blocked, email verification,
            2FA adoption or role distribution. For money, orders, subscriptions, revenue or churn use billing_* tools.
            Dates are ISO 8601 UTC. Contains no PII (no emails, names, IPs) — only aggregates.
            TXT,
        annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false),
    )]
    public function getSummary(int $tenantId): array
    {
        return $this->executor->run(
            new ToolInvocation(self::TOOL_GET_SUMMARY, BackendService::Users, [ArgumentValidator::TENANT_ID => $tenantId]),
            function () use ($tenantId): array {
                $this->validator->assertTenantId($tenantId);

                return $this->client->get(\sprintf('tenants/%d/users-summary', $tenantId));
            },
        );
    }

    /**
     * @param int $tenantId Tenant (organisation) ID, >= 1. The same tenantId is used by the billing_* tools.
     * @param int $days     Number of most recent days to include, 1..90 (default 30). Today is the last day.
     *
     * @return array<mixed>
     */
    #[McpTool(
        name: self::TOOL_GET_LOGIN_ACTIVITY,
        title: 'Users: daily login activity',
        description: <<<'TXT'
            Users service (accounts & security domain). Returns login activity of one tenant per day for the last
            N days: daily[] with date (YYYY-MM-DD, UTC), successful_logins, failed_logins, unique_active_users,
            password_resets; plus period_start, period_end, generated_at.
            Use for questions about logins, failed login attempts, user activity/engagement over time and password
            resets. For revenue, orders or customer churn use billing_* tools.
            Contains no PII — only daily counts.
            TXT,
        annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false),
    )]
    public function getLoginActivity(int $tenantId, int $days = self::DEFAULT_DAYS): array
    {
        return $this->executor->run(
            new ToolInvocation(self::TOOL_GET_LOGIN_ACTIVITY, BackendService::Users, [ArgumentValidator::TENANT_ID => $tenantId, self::PARAM_DAYS => $days]),
            function () use ($tenantId, $days): array {
                $this->validator->assertTenantId($tenantId);
                $this->validator->assertInRange(self::PARAM_DAYS, $days, self::MIN_DAYS, self::MAX_DAYS);

                return $this->client->get(\sprintf('tenants/%d/login-activity', $tenantId), [self::PARAM_DAYS => $days]);
            },
        );
    }

    /**
     * @param int $tenantId Tenant (organisation) ID, >= 1. The same tenantId is used by the billing_* tools.
     * @param int $limit    Maximum number of most recent events, 1..200 (default 50).
     *
     * @return array<mixed>
     */
    #[McpTool(
        name: self::TOOL_GET_SECURITY_EVENTS,
        title: 'Users: recent security events',
        description: <<<'TXT'
            Users service (accounts & security domain). Returns the most recent security events of one tenant,
            newest first: events[] with id, type (failed_login_burst | account_locked | 2fa_disabled |
            password_reset | role_changed), user_id (internal ID, may be null) and occurred_at (ISO 8601 UTC).
            Use for questions about suspicious activity, account lockouts, disabled 2FA, role changes or
            password resets. user_id is the same ID the Billing service uses, so it can be matched with
            billing_get_churn_risk results. Contains no PII — no emails, names or IPs.
            TXT,
        annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false),
    )]
    public function getSecurityEvents(int $tenantId, int $limit = self::DEFAULT_LIMIT): array
    {
        return $this->executor->run(
            new ToolInvocation(self::TOOL_GET_SECURITY_EVENTS, BackendService::Users, [ArgumentValidator::TENANT_ID => $tenantId, self::PARAM_LIMIT => $limit]),
            function () use ($tenantId, $limit): array {
                $this->validator->assertTenantId($tenantId);
                $this->validator->assertInRange(self::PARAM_LIMIT, $limit, self::MIN_LIMIT, self::MAX_LIMIT);

                return $this->client->get(\sprintf('tenants/%d/security-events', $tenantId), [self::PARAM_LIMIT => $limit]);
            },
        );
    }
}
