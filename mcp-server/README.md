# MCP server (internal AI)

An MCP server through which the team asks AI (Claude) about data from two backend services. It's a thin
read-only adapter: each MCP tool is one `GET` to a backend's internal API. No database,
no business logic, no message broker.

## Architecture

```
 Claude (claude.ai / Claude Code / Inspector)
        │  HTTPS, OAuth 2.0 (Google Workspace), MCP Streamable HTTP
        ▼
 ┌──────────────┐  GET /internal/ai/v1/...  ┌──────────────────┐
 │  mcp-server  │ ─────────────────────────▶│  users-service   │  Symfony: users, logins, roles, 2FA, security
 │ (this repo)  │  Bearer service token     └──────────────────┘
 │  BFF for AI  │  X-Acting-User, traceparent,┌──────────────────┐
 │  no DB       │ ─────────────────────────▶│ billing-service  │  Laravel: orders, payments, subscriptions, RFM, churn
 └──────────────┘  X-Request-Id              └──────────────────┘
```

- **Microservices.** Each service has its own repository, database, container and deploy. Nobody reads another
  service's database. Sync traffic goes only over the internal API, async traffic over RabbitMQ events
  (`user.*`: users-service → billing-service). This server doesn't need the broker.
- **Network.** Services find each other by DNS names (`users-service`, `billing-service`, `mcp-server`).
  `/internal/*` is never published through ingress. Only `/mcp`, `/.well-known/oauth-protected-resource*`
  and `/health/*` of this server are public.
- **Contract.** `/internal/ai/v1/...`, `GET` only, headers `Authorization: Bearer <service token>` and
  `X-Acting-User: <email>`, codes 401/403/404/405/422/429. The JSON shapes are in each owner's OpenAPI spec
  (`public/openapi/internal-ai.yaml` in users-service and billing-service). Breaking changes → `/v2`, and
  v1 lives until this server migrates.
- **Tracing.** For every `tools/call` the server continues the incoming trace (or starts a new one), sends
  `traceparent` (W3C, new span per call) and `X-Request-Id` to the backend, and writes `request_id`/`trace_id`
  into every log line. The response carries `X-Request-Id`.
- **Partial failure.** A backend outage affects only its own tools. The model gets
  "Сервіс billing тимчасово недоступний" ("billing service temporarily unavailable"), and the users_* tools keep working.
  `/health/ready` doesn't check the backends.

### Tools

| Tool | Service | Endpoint | Parameters |
|---|---|---|---|
| `users_get_summary` | users | `GET /internal/ai/v1/tenants/{tenantId}/users-summary` | `tenantId` |
| `users_get_login_activity` | users | `.../login-activity?days=N` | `tenantId`, `days` 1–90 (30) |
| `users_get_security_events` | users | `.../security-events?limit=N` | `tenantId`, `limit` 1–200 (50) |
| `billing_get_rfm_summary` | billing | `.../rfm-summary` | `tenantId` |
| `billing_get_churn_risk` | billing | `.../churn-risk?limit=N` | `tenantId`, `limit` 1–200 (50) |
| `billing_get_revenue_summary` | billing | `.../revenue-summary?days=N` | `tenantId`, `days` 1–365 (30) |

Ranges are checked here before the HTTP request. A violation comes back as `isError: true` with the text
"Невірні параметри: limit" ("Invalid parameters: limit"). Backend errors map to short messages with no internal details:
404 → "Tenant не знайдено" ("Tenant not found"), 422 → "Невірні параметри: <param>", anything else / network error → "Сервіс <users|billing> тимчасово недоступний".
Retry: one attempt with backoff, only on network errors and 502/503/504. 4xx is never retried.

## Stack

Symfony 8.1 (PHP ≥ 8.4; the image uses PHP 8.5), FrankenPHP in worker mode, `symfony/mcp-bundle` 0.14
(`mcp/sdk` 0.8, HTTP transport only), `symfony/http-client`, `security-bundle`, `rate-limiter`, Monolog.

```
src/
  Backend/InternalApiClient.php      the single backend client: headers, tracing, error mapping
  Controller/                        /.well-known/oauth-protected-resource, /health/live, /health/ready
  Dto/                               ToolInvocation, GoogleTokenInfo
  Enums/                             BackendService, AuditResult
  EventListener/                     Origin allowlist, rate limiter, X-Request-Id
  Exception/                         TenantNotFound / InvalidArguments / BackendUnavailable
  Mcp/Tool/{Users,Billing}Tools.php  the 6 tools (#[McpTool])
  Mcp/ToolExecutor.php               span + audit + domain error → isError
  Observability/                     TraceContext (ResetInterface), Monolog processor
  Security/                          GoogleTokenHandler, 401 entry point, metadata
config/packages/{mcp,framework,security,monolog}.yaml
tests/{Unit,Integration,Functional,Contract,Fixtures}
postman/                             collection + local environment
docker/                              Caddyfile, php.ini, WireMock mappings
```

## Environment variables

| Variable | Description |
|---|---|
| `USERS_SERVICE_INTERNAL_API` | base URL of users-service (`http://users-service`), without `/internal/ai/v1` |
| `USERS_SERVICE_TOKEN` | service token for users-service |
| `BILLING_SERVICE_INTERNAL_API` | base URL of billing-service (`http://billing-service`) |
| `BILLING_SERVICE_TOKEN` | service token for billing-service |
| `GOOGLE_CLIENT_ID` | OAuth Client ID; a token with a different `aud` is rejected |
| `ALLOWED_EMAIL_DOMAIN` | Google Workspace domain (`example.com`) |
| `MCP_PUBLIC_URL` | public URL of the MCP endpoint (`https://mcp.example.com/mcp`) |
| `MCP_ALLOWED_ORIGINS` | Origin allowlist, comma-separated |
| `APP_SECRET`, `APP_ENV` | standard Symfony |

All of them with comments are in `.env.example`. Secrets go only in `.env.local` (git-ignored) or the orchestrator's secrets.

### Where to get the service tokens

Each backend issues its own token. The backend stores only the **sha256 hashes** of tokens
(`MCP_SERVICE_TOKEN_HASHES`, a JSON array, several entries for rotation):

```bash
TOKEN=$(openssl rand -hex 32)                       # → BILLING_SERVICE_TOKEN / USERS_SERVICE_TOKEN of this server
printf '%s' "$TOKEN" | shasum -a 256 | cut -d' ' -f1  # → MCP_SERVICE_TOKEN_HASHES of the matching backend
```

Use a separate token for users-service and for billing-service. Locally both backends accept `dev-mcp-token`.

## Running locally

```bash
docker network create internal-ai 2>/dev/null || true
cp .env.example .env.local                 # fill in GOOGLE_CLIENT_ID, ALLOWED_EMAIL_DOMAIN

# A) next to the real backends (start users-service and billing-service with their own compose.yaml)
docker compose up -d --build --wait

# B) without backends: WireMock stubs serving tests/Fixtures
docker compose -f compose.yaml -f compose.stubs.yaml up -d --build --wait
```

The server listens on `http://localhost:8080`: MCP at `/mcp`, metadata at `/.well-known/oauth-protected-resource`.

Tests and linters (on the host, PHP ≥ 8.4):

```bash
composer install
composer test        # PHPUnit: no real network (MockHttpClient)
composer phpstan     # level 8
composer cs          # PHP-CS-Fixer
composer contract:sync && composer test   # refresh the backends' OpenAPI specs and re-check the fixtures
```

Backend response fixtures (`tests/Fixtures/{Users,Billing}`) are validated against the OpenAPI specs
(`tests/Contract/openapi/*` is a snapshot from the neighbouring repositories). When a contract changes, update the fixtures first.

## Google Cloud setup

1. **OAuth consent screen**: type **Internal** (only accounts in your Workspace), scopes `openid`, `email`.
2. **Credentials → Create OAuth client ID**, type *Web application*. Its Client ID goes into `GOOGLE_CLIENT_ID`.
3. **Authorized redirect URIs**: add the redirect URI of every client that will log in:
   - Postman: `https://oauth.pstmn.io/v1/callback`;
   - claude.ai (custom connector), Claude Code, MCP Inspector: **take the exact values from their current documentation**
     (they differ and change between versions).
4. Google has no Dynamic Client Registration, so the Client ID (and Client Secret, which Google requires for
   Web clients even with PKCE) have to be entered in the client by hand.

How the check works: the client gets a Google **access token** and sends it as `Authorization: Bearer`.
`GoogleTokenHandler` checks it via `oauth2.googleapis.com/tokeninfo` (POST, so the token stays out of the URL) and requires:
`aud === GOOGLE_CLIENT_ID`, `email_verified === "true"`, email ends with `@ALLOWED_EMAIL_DOMAIN`.
A successful result is cached for ≤ 5 minutes under the sha256 of the token.

## Connecting clients

**Claude Code**:

```bash
claude mcp add --transport http internal-ai https://mcp.example.com/mcp
```

Then `/mcp` inside Claude Code → authenticate. The client finds the authorization server via
`WWW-Authenticate: Bearer resource_metadata="…"` → `/.well-known/oauth-protected-resource` → `accounts.google.com`.
Because there's no DCR, you need to pass the Client ID/Secret; check the exact flags in the documentation for your Claude Code version.

**claude.ai**: Settings → Connectors → *Add custom connector* → URL `https://mcp.example.com/mcp`, and under
*Advanced settings* the OAuth Client ID / Client Secret from Google Cloud. Add the connector's redirect URI to the OAuth client (step 3).
`https://claude.ai` must be in `MCP_ALLOWED_ORIGINS` (it already is by default).

**MCP Inspector**:

```bash
npx @modelcontextprotocol/inspector
```

Transport *Streamable HTTP*, URL `http://localhost:8080/mcp`, under Authentication either a Bearer token
(a Google access token, e.g. from Postman) or OAuth with your Client ID. `http://localhost:6274` is already in the Origin allowlist.

## Postman

1. Import `postman/mcp-server.postman_collection.json` and `postman/local.postman_environment.json`.
   For real values make a personal copy of the environment (`postman/*.local.*` is git-ignored).
2. Fill in `google_client_id` / `google_client_secret` in the environment.
3. Collection → *Authorization* (OAuth 2.0, Authorization Code + PKCE, Google, scopes `openid email`) →
   *Get New Access Token* → log in with your Workspace account → *Use Token*. Or paste the token by hand into
   `google_access_token`. The token has to be issued to your OAuth client: a token from `gcloud auth print-access-token`
   has a different `aud` and gets 401.
4. Order in the **MCP** folder: `initialize` (the test saves `Mcp-Session-Id` into `mcp_session_id`) →
   `notifications/initialized` → `tools/list` → `tools/call …` → `close session (DELETE)`.
   Responses are `application/json` (the bundle only switches to SSE `text/event-stream` when the server itself asks the client
   something; the tests handle both formats).
5. The **Backends** folder calls the internal API directly (service token + `X-Acting-User`), for debugging.

Without interactive OAuth (Public, Auth checks, Backends) the collection runs from the CLI:

```bash
npx newman run postman/mcp-server.postman_collection.json -e postman/local.postman_environment.json \
  --folder Public --folder "Auth checks" --folder Backends
```

## MCP sessions, FrankenPHP and multiple replicas

- Worker mode: the kernel lives across requests. Request state (`TraceContext`) implements `ResetInterface`,
  and there's no state in `static`.
- MCP sessions (`symfony/mcp-bundle`, `session.store: cache`) are stored in `cache.app` via PSR-16. Rate-limiter state and
  the Google token cache live there too. By default `cache.app` is the filesystem inside the container. **That's correct for a single replica**,
  but it's lost on restart (the client gets 404 "Session not found" and simply runs `initialize` again).
- **For several replicas**, `cache.app` must be shared: `framework.cache.app: cache.adapter.redis` +
  `default_redis_provider: '%env(REDIS_URL)%'` (see `config/packages/cache.yaml`), plus `ext-redis` in the image.
  The alternative is sticky routing by the `Mcp-Session-Id` header on the ingress, but then the rate limiter still counts per replica.
  Do not use the `array` adapter: in worker mode it is cleared between requests.
- The stateless MCP era (2026-07-28) needs no sessions. The bundle serves both eras on the same endpoint.

## Security

- The MCP route is behind a stateless firewall. Public endpoints are only metadata and `/health/*`.
- The SDK's own DNS-rebinding check is disabled (`allowed_hosts: false`, because it only allows localhost). Instead,
  `OriginCheckListener` returns 403 for any `Origin` outside `MCP_ALLOWED_ORIGINS` (it runs before authentication).
  Requests without `Origin` (server-side clients) are not affected. The `Host` check belongs to the ingress.
- Rate limit: 60 requests/min per user (email) → 429 + `Retry-After`.
- Audit: exactly one JSON entry in stderr per `tools/call` (channel `audit`): `email`, `tool`, `service`,
  `arguments`, `result_status` (`success|tool_error|internal_error`), `duration_ms`, `request_id`, `trace_id`.
  Calls the SDK rejects at the JSON schema level (wrong argument type) never reach a tool
  and are not audited.
- Tokens (Google and service tokens) are never logged and never returned. Backend error bodies aren't passed on to the model.

## Left to configure by hand

- Google Cloud: consent screen (Internal), OAuth client, clients' redirect URIs.
- Ingress/TLS: publish only `/mcp`, `/.well-known/oauth-protected-resource*`, `/health/*`; **never** `/internal/*`
  of the backends; set `MCP_PUBLIC_URL` to the real https address.
- Secrets: `USERS_SERVICE_TOKEN` / `BILLING_SERVICE_TOKEN` here and their sha256 in `MCP_SERVICE_TOKEN_HASHES`
  of users-service and billing-service; `GOOGLE_CLIENT_ID`, `ALLOWED_EMAIL_DOMAIN`, `APP_SECRET`.
- Redis for `cache.app` once there is more than one replica.
- Check against a live client that the connector works with Google as the authorization server (whether Google accepts the
  `resource` parameter of RFC 8707 depends on the client).
