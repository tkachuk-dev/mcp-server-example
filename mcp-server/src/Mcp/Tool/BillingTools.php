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
 * Tools backed by the Billing service (Laravel): orders, payments, subscriptions, revenue, RFM, churn.
 * Ranges mirror the Billing internal API contract v1.
 */
final readonly class BillingTools
{
    public const string TOOL_GET_RFM_SUMMARY = 'billing_get_rfm_summary';
    public const string TOOL_GET_CHURN_RISK = 'billing_get_churn_risk';
    public const string TOOL_GET_REVENUE_SUMMARY = 'billing_get_revenue_summary';

    public const int DEFAULT_LIMIT = 50;
    public const int MIN_LIMIT = 1;
    public const int MAX_LIMIT = 200;
    public const int DEFAULT_DAYS = 30;
    public const int MIN_DAYS = 1;
    public const int MAX_DAYS = 365;

    private const string PARAM_DAYS = 'days';
    private const string PARAM_LIMIT = 'limit';

    public function __construct(
        #[Autowire(service: 'app.backend.billing')]
        private InternalApiClient $client,
        private ToolExecutor $executor,
        private ArgumentValidator $validator,
    ) {
    }

    /**
     * @param int $tenantId Tenant (organisation) ID, >= 1. The same tenantId is used by the users_* tools.
     *
     * @return array<mixed>
     */
    #[McpTool(
        name: self::TOOL_GET_RFM_SUMMARY,
        title: 'Billing: RFM segments',
        description: <<<'TXT'
            Billing service (orders & money domain). Returns RFM customer segmentation of one tenant: currency
            (ISO 4217) and segments[] (champions | loyal | potential | at_risk | hibernating | lost) with
            customers_count, avg_recency_days, avg_frequency (paid orders per customer) and avg_monetary_minor
            (money in minor units, e.g. cents/kopiyky — divide by 100 for display), plus generated_at.
            Use for questions about customer value, loyalty and segment sizes. For logins, 2FA, roles or blocked
            accounts use users_* tools. Contains no PII — only aggregates.
            TXT,
        annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false),
    )]
    public function getRfmSummary(int $tenantId): array
    {
        return $this->executor->run(
            new ToolInvocation(self::TOOL_GET_RFM_SUMMARY, BackendService::Billing, [ArgumentValidator::TENANT_ID => $tenantId]),
            function () use ($tenantId): array {
                $this->validator->assertTenantId($tenantId);

                return $this->client->get(\sprintf('tenants/%d/rfm-summary', $tenantId));
            },
        );
    }

    /**
     * @param int $tenantId Tenant (organisation) ID, >= 1. The same tenantId is used by the users_* tools.
     * @param int $limit    Maximum number of customers, highest risk first, 1..200 (default 50).
     *
     * @return array<mixed>
     */
    #[McpTool(
        name: self::TOOL_GET_CHURN_RISK,
        title: 'Billing: customers at churn risk',
        description: <<<'TXT'
            Billing service (orders & money domain). Returns customers of one tenant with the highest churn risk,
            sorted by risk descending: customers[] with user_id, churn_score (0..1, higher = more likely to churn),
            segment (RFM segment) and last_order_at (ISO 8601 UTC, may be null); blocked/deleted users are excluded.
            Use for questions about churn, retention and who to win back. user_id is the same internal ID as in the
            Users service, so results can be matched with users_get_security_events. Contains no PII — no emails or
            names, only internal IDs.
            TXT,
        annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false),
    )]
    public function getChurnRisk(int $tenantId, int $limit = self::DEFAULT_LIMIT): array
    {
        return $this->executor->run(
            new ToolInvocation(self::TOOL_GET_CHURN_RISK, BackendService::Billing, [ArgumentValidator::TENANT_ID => $tenantId, self::PARAM_LIMIT => $limit]),
            function () use ($tenantId, $limit): array {
                $this->validator->assertTenantId($tenantId);
                $this->validator->assertInRange(self::PARAM_LIMIT, $limit, self::MIN_LIMIT, self::MAX_LIMIT);

                return $this->client->get(\sprintf('tenants/%d/churn-risk', $tenantId), [self::PARAM_LIMIT => $limit]);
            },
        );
    }

    /**
     * @param int $tenantId Tenant (organisation) ID, >= 1. The same tenantId is used by the users_* tools.
     * @param int $days     Number of most recent days to include, 1..365 (default 30). Today is the last day.
     *
     * @return array<mixed>
     */
    #[McpTool(
        name: self::TOOL_GET_REVENUE_SUMMARY,
        title: 'Billing: daily revenue',
        description: <<<'TXT'
            Billing service (orders & money domain). Returns revenue of one tenant per day for the last N days:
            currency (ISO 4217) and daily[] with date (YYYY-MM-DD, UTC), revenue_minor, orders_count,
            avg_order_value_minor, refunds_minor, refunds_count, new_subscriptions, cancelled_subscriptions; plus
            period_start, period_end, generated_at. All *_minor amounts are integers in minor units (cents/kopiyky).
            Use for questions about revenue, sales, orders, average check, refunds and subscription growth.
            For logins or security use users_* tools. Contains no PII and no payment data — only aggregates.
            TXT,
        annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false),
    )]
    public function getRevenueSummary(int $tenantId, int $days = self::DEFAULT_DAYS): array
    {
        return $this->executor->run(
            new ToolInvocation(self::TOOL_GET_REVENUE_SUMMARY, BackendService::Billing, [ArgumentValidator::TENANT_ID => $tenantId, self::PARAM_DAYS => $days]),
            function () use ($tenantId, $days): array {
                $this->validator->assertTenantId($tenantId);
                $this->validator->assertInRange(self::PARAM_DAYS, $days, self::MIN_DAYS, self::MAX_DAYS);

                return $this->client->get(\sprintf('tenants/%d/revenue-summary', $tenantId), [self::PARAM_DAYS => $days]);
            },
        );
    }
}
