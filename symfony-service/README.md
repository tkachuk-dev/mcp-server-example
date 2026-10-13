# Users service (Symfony)

Users, authentication, roles, 2FA, login journal and security events. Source of truth for `user_id`.
This repository also exposes the read-only **internal AI API v1** consumed by the MCP server.

## Internal AI API (`/internal/ai/v1`, GET only)

| Endpoint | Query | Description |
|---|---|---|
| `/tenants/{tenantId}/users-summary` | — | totals, active in 30 days, blocked, unverified email, 2FA ratio, users per role |
| `/tenants/{tenantId}/login-activity` | `days` 1–90 (30) | per day: successful/failed logins, unique active users, password resets |
| `/tenants/{tenantId}/security-events` | `limit` 1–200 (50) | latest events: `type`, internal `user_id`, `occurred_at` |

- Contract: `public/openapi/internal-ai.yaml` (entry point `public/openapi/openapi.yaml`, shared schemas in `components.yaml`).
- Auth: `Authorization: Bearer <service token>` + `X-Acting-User: <email>` (firewall `internal_ai`, role `ROLE_AI_READ`).
- Errors: `{"message": "..."}`; 422 → `{"message": "...", "errors": {"<param>": ["..."]}}`.
- No PII. Dates ISO 8601 UTC. Cache: 10 min (security events: 1 min). Rate limit: 120/min per actor.
- Tracing: `traceparent` / `X-Request-Id` accepted or generated, added to every log line, `X-Request-Id` returned.
- Audit: one JSON line per request in the `ai_audit` channel (stderr).

## Service token

```bash
TOKEN=$(openssl rand -hex 32)                       # give to the MCP server as USERS_SERVICE_TOKEN
printf '%s' "$TOKEN" | shasum -a 256 | cut -d' ' -f1  # put the hash into MCP_SERVICE_TOKEN_HASHES
# rotation: MCP_SERVICE_TOKEN_HASHES='["<new hash>","<old hash>"]', then drop the old one
```

## Local run

```bash
docker network create internal-ai   # once, shared with mcp-server and billing-service
docker compose up -d --build
docker compose exec users-service bin/console doctrine:migrations:migrate -n
docker compose exec users-service bin/console doctrine:fixtures:load -n
curl -H 'Authorization: Bearer dev-mcp-token' -H 'X-Acting-User: dev@example.com' \
  localhost:8081/internal/ai/v1/tenants/1/users-summary
```

Ports: `8081` → internal listener (`:80`, what `http://users-service` serves in the cluster),
`8091` → public listener (`:8080`, `/internal/*` → 404) — the only one an ingress may target.

## Quality

```bash
vendor/bin/phpunit            # SQLite + fixtures, each test in a rolled-back transaction
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix   # on PHP > 8.4 locally: PHP_CS_FIXER_IGNORE_ENV=1
```

## Not implemented here

Publishing `user.registered`, `user.blocked`, `user.deleted`, `user.role_changed` to RabbitMQ
(Symfony Messenger + AMQP transport, ideally via an outbox) is a separate task; `/health/ready`
will then also check the broker.
