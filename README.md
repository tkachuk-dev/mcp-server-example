# MCP Server Example

A monorepo with three microservices that let the team ask AI (Claude) questions about
users and billing data through an MCP server.

```
 Claude (claude.ai / Claude Code / MCP Inspector)
        │  HTTPS, OAuth 2.0 (Google Workspace), MCP Streamable HTTP
        ▼
 ┌──────────────┐  GET /internal/ai/v1/...   ┌──────────────────┐
 │  mcp-server  │ ──────────────────────────▶│  users-service   │  Symfony · PostgreSQL · Redis
 │  BFF for AI  │  Bearer service token      └──────────────────┘
 │  no DB       │  X-Acting-User             ┌──────────────────┐
 │              │ ──────────────────────────▶│ billing-service  │  Laravel · PostgreSQL · Redis
 └──────────────┘  traceparent, X-Request-Id └──────────────────┘
                                                      ▲
                     user.* events (RabbitMQ, planned) │ users-service → billing-service
```

| Service | Directory | Stack | Responsibility |
|---|---|---|---|
| **users-service** | [`symfony-service/`](symfony-service/) | Symfony 7.4, FrankenPHP (worker) | Users, authentication, roles, 2FA, login journal, security events. Source of truth for `user_id`. |
| **billing-service** | [`laravel-service/`](laravel-service/) | Laravel 12, FrankenPHP | Orders, payments, refunds, subscriptions, revenue, RFM segmentation, churn risk. |
| **mcp-server** | [`mcp-server/`](mcp-server/) | Symfony 8.1, `symfony/mcp-bundle`, FrankenPHP (worker) | Thin read-only MCP adapter: one tool = one `GET` to a backend's internal API. |

## Architecture rules

- **Database per service.** No service reads another service's database. Each service has its own
  container, can be deployed and scaled on its own, and is configured only through env.
- **Internal API** `/internal/ai/v1/...`: `GET` only, headers `Authorization: Bearer <service token>` and
  `X-Acting-User: <email>`, error codes 401/403/404/405/422/429. It's reachable only inside the private
  network and is never published through the ingress.
- **Contract.** The JSON shapes are defined only in each owner service's OpenAPI 3.1 spec
  (`public/openapi/internal-ai.yaml`). mcp-server checks its fixtures against those specs in contract
  tests. A breaking change means `/v2`, and v1 stays until mcp-server migrates.
- **No PII** in the internal API: only internal `user_id`, aggregates, event types and dates
  (ISO 8601 UTC). Money is in minor units plus an ISO 4217 currency code.
- **Tracing.** `traceparent` (W3C Trace Context) and `X-Request-Id` travel across all services and appear
  in every JSON log line in stderr.
- `user_id` is the same in both backends, so the model can correlate users and billing data itself.

## MCP tools

| Tool | Backend endpoint | Parameters |
|---|---|---|
| `users_get_summary` | `GET /tenants/{id}/users-summary` | `tenantId` |
| `users_get_login_activity` | `GET /tenants/{id}/login-activity` | `tenantId`, `days` 1–90 (30) |
| `users_get_security_events` | `GET /tenants/{id}/security-events` | `tenantId`, `limit` 1–200 (50) |
| `billing_get_rfm_summary` | `GET /tenants/{id}/rfm-summary` | `tenantId` |
| `billing_get_churn_risk` | `GET /tenants/{id}/churn-risk` | `tenantId`, `limit` 1–200 (50) |
| `billing_get_revenue_summary` | `GET /tenants/{id}/revenue-summary` | `tenantId`, `days` 1–365 (30) |

## Quick start (local, Docker)

The quickest way is the root `Makefile` (`make help` lists every command):

```bash
make up          # network + all three services, users-service migrations
make seed        # demo data in both backends
make health      # health checks + one internal request per backend
make sh-users    # shell in users-service (also sh-billing, sh-mcp)
make db-billing  # psql (also db-users, redis-users, redis-billing)
make test lint   # all test suites and linters (on the host)
```

The same steps by hand:

```bash
docker network create internal-ai          # once: shared network for all three services

# users-service → http://localhost:8081 (public entry for the ingress: :8091)
cd symfony-service
docker compose up -d --build --wait
docker compose exec users-service bin/console doctrine:migrations:migrate -n
docker compose exec users-service bin/console doctrine:fixtures:load -n

# billing-service → http://localhost:8082 (migrations run on start)
cd ../laravel-service
docker compose up -d --build --wait
docker compose exec billing-service php artisan db:seed --force

# mcp-server → http://localhost:8080/mcp
cd ../mcp-server
cp .env.example .env.local                 # fill in GOOGLE_CLIENT_ID, ALLOWED_EMAIL_DOMAIN, service tokens
docker compose up -d --build --wait
```

To run mcp-server without the backends, use the WireMock stubs:
`docker compose -f compose.yaml -f compose.stubs.yaml up -d --build --wait`.

Check that the services are up:

```bash
curl localhost:8080/health/ready
curl -H 'Authorization: Bearer dev-mcp-token' -H 'X-Acting-User: dev@example.com' \
  localhost:8081/internal/ai/v1/tenants/1/users-summary
curl -H 'Authorization: Bearer dev-mcp-token' -H 'X-Acting-User: dev@example.com' \
  'localhost:8082/internal/ai/v1/tenants/1/churn-risk?limit=5'
```

`dev-mcp-token` is a placeholder for local development only.

| Service | Host port | DNS name in the `internal-ai` network |
|---|---|---|
| mcp-server | 8080 | `mcp-server` |
| users-service | 8081 (internal), 8091 (public entry) | `users-service` |
| billing-service | 8082 | `billing-service` |

## Tests and linters

| Service | Tests | Static analysis / code style |
|---|---|---|
| symfony-service | `vendor/bin/phpunit` | `vendor/bin/phpstan analyse`, `vendor/bin/php-cs-fixer fix` |
| laravel-service | `php artisan test` | `vendor/bin/phpstan analyse`, `vendor/bin/pint` |
| mcp-server | `composer test` | `composer phpstan`, `composer cs` |

The tests make no real network calls and need no Docker: the backends use SQLite and mocked caches,
and mcp-server uses `MockHttpClient`.
After a backend's OpenAPI spec changes, run `composer contract:sync && composer test` in `mcp-server/`.

The Postman collection and environment are in [`mcp-server/postman/`](mcp-server/postman/). Requests that
don't need interactive OAuth run with
`npx newman run postman/mcp-server.postman_collection.json -e postman/local.postman_environment.json --folder Public --folder "Auth checks" --folder Backends`.

## Service tokens

Each backend stores only the sha256 hashes of accepted tokens. Keeping several hashes allows rotation.

```bash
TOKEN=$(openssl rand -hex 32)
printf '%s' "$TOKEN" | shasum -a 256 | cut -d' ' -f1
# backend:    MCP_SERVICE_TOKEN_HASHES='["<hash>"]'
# mcp-server: USERS_SERVICE_TOKEN=<token> / BILLING_SERVICE_TOKEN=<token>
```

## Not implemented yet / manual setup

- **RabbitMQ events** `user.registered`, `user.blocked`, `user.deleted`, `user.role_changed`:
  users-service doesn't publish them yet, and billing-service has the `user_projections` table but no
  consumer.
- **Google Cloud:** an Internal OAuth consent screen and an OAuth Web client (`openid email`).
  Each MCP client needs the Client ID and Secret entered by hand.
  See [`mcp-server/README.md`](mcp-server/README.md).
- **Ingress / TLS:** publish only `/mcp`, `/.well-known/oauth-protected-resource*` and `/health/*` of
  mcp-server. Never publish the backends' `/internal/*`; their public entry listeners return 404 for it.
- **Secrets:** real service tokens and their hashes, `APP_SECRET` / `APP_KEY`.
- **Redis** for mcp-server's `cache.app` when it runs more than one replica (MCP sessions, rate limiter,
  Google token cache).

## Repository layout

```
.
├── symfony-service/   # users-service: README.md, CLAUDE.md, PROMPT.md, public/openapi/
├── laravel-service/   # billing-service: CLAUDE.md, PROMPT.md, public/openapi/
└── mcp-server/        # MCP server: README.md (Google, clients, Postman), CLAUDE.md, PROMPT.md, postman/
```

Each service has its own `CLAUDE.md` with its coding rules and a `PROMPT.md` with the original task.
