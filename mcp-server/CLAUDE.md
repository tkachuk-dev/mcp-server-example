# CLAUDE.md — MCP Server (Symfony) Guidelines

## General Principles

- **KISS**: Keep It Simple. Prefer straightforward solutions to clever ones.
- Methods must be short and focused — one responsibility per method.
- Avoid long argument lists. If a method needs more than 3–4 arguments, group them into a DTO or value object.

## Service Boundaries

- This service is a **thin adapter**: MCP tools → HTTP GET to the internal API of the Users service (Symfony) and the Billing service (Laravel). Nothing else.
- **No database, no Doctrine, no message broker.** Do not add a persistence layer, even for caching beyond `cache.app`.
- **No business logic.** Do not compute, join, filter, or aggregate data from the backends. If a tool needs data the backends do not expose, the backend gets a new endpoint (in its own repo), not a workaround here.
- Only read-only (`GET`) calls to `/internal/ai/v1/...`. Never call public or write endpoints of other services.
- Do not merge Users and Billing data in a single tool. The model can correlate them itself via `user_id`, which is the same in both services.

## MCP Tools

- Tools live in `src/Mcp/Tool/`, one class per backend service: `UsersTools`, `BillingTools`. Each public method carries exactly one `#[McpTool]`.
- Tool names: `<service>_<verb>_<noun>` in snake_case, e.g. `users_get_login_activity`, `billing_get_churn_risk`. Renaming a tool is a breaking change for clients — avoid it.
- Every tool has a description written for the model: which service it calls, what it returns, when to use it (and when to use the other service instead), units (money in minor units + currency, dates in ISO 8601 UTC), and that the data contains no PII.
- Parameters are scalar and typed (`int $tenantId`, `int $limit = 50`). Ranges and defaults are typed class constants that mirror the backend contract (`public const int MAX_LIMIT = 200;`) and are validated **before** the HTTP call.
- Tools are thin: validate arguments, call the service client, return the decoded backend payload. No reshaping beyond what is needed for the MCP result format.
- Errors returned to the model are short, human-readable, and contain no internal details (URLs, stack traces, backend bodies): "Tenant не знайдено", "Невірні параметри: limit", "Сервіс billing тимчасово недоступний".

## Backend HTTP Clients

- One scoped HTTP client per backend (`users_service.api`, `billing_service.api`) configured in `config/packages/framework.yaml`: `base_uri`, Bearer service token, `timeout: 5`, `max_duration: 10`. Base URLs are internal DNS names from env, never hardcoded.
- Tools never use `HttpClientInterface` directly — they go through a single shared client wrapper (`src/Backend/InternalApiClient.php`) injected per service via `#[Autowire(service: ...)]`. Header handling, tracing, retries, and error mapping live only there.
- Every request sends `X-Acting-User` (email of the authenticated user), `traceparent`, and `X-Request-Id`.
- Retry once with backoff only on network errors and `502/503/504` (`RetryableHttpClient`). Never retry `4xx`.
- Map backend responses to domain exceptions in `src/Exception/`: `404` → `TenantNotFoundException`, `422` → `InvalidArgumentsException`, other `≥400` / network errors → `BackendUnavailableException`. Never forward a backend error body to the model.
- A failure of one backend must not affect tools of the other.

## Authentication & Security

- The MCP route is behind a stateless firewall with `GoogleTokenHandler` (`src/Security/`). Do not weaken any of its checks: `aud === GOOGLE_CLIENT_ID`, `email_verified === "true"`, email domain is `ALLOWED_EMAIL_DOMAIN`.
- Never log, cache by raw value, or return access tokens or service tokens. Cache keys use `sha256` of the token.
- The `Origin` allowlist check (DNS rebinding protection) stays enabled in every environment except tests.
- Public endpoints are only `/.well-known/oauth-protected-resource` and `/health/*`. Everything else requires authentication.

## FrankenPHP Worker Mode

- The kernel is reused between requests. **No request-scoped data in service properties** unless the service implements `ResetInterface` and clears it in `reset()`.
- No `static` state, no singletons, no global variables.
- Do not rely on `$_SERVER` / `$_ENV` at runtime — read configuration through the container.
- Anything that holds connections or buffers (HTTP clients, loggers) must be safe to reuse across requests.

## DTOs

- DTOs are only allowed inside the `src/Dto/` directory.
- Do not create DTO-like classes in other directories (Tools, Services, Security, etc.).
- Use DTOs for internal structures (e.g. the result of Google token introspection, the acting user context). Backend payloads are passed through as decoded arrays — do not mirror the backend schemas in PHP classes.

## Symfony Configuration

- Use PHP attributes for service configuration instead of `services.yaml` (e.g., `#[Autowire]`, `#[AutoconfigureTag]`, `#[AsEventListener]`, `#[McpTool]`, etc.).
- Do not register services, tags, or bindings manually in `services.yaml` when an attribute exists for the same purpose.
- Always use `#[AutowireIterator]` for tagged iterators. Never use `#[TaggedIterator]` — it is a deprecated alias.
- `symfony/mcp-bundle` is experimental: before using an attribute, config key, or class from it, check its code in `vendor/` — do not rely on memory or older docs.

## Configuration & Environment

- All configuration comes from env variables. Every new variable is added to `.env.example` with a placeholder value and a one-line comment.
- Secrets (`*_SERVICE_TOKEN`, Google client data) never go into committed `.env` files.

## Logging & Observability

- Logs are JSON to `stderr` (Monolog). Every log line carries `request_id` and `trace_id` (Monolog processor).
- Each `tools/call` produces exactly one audit log entry: email, tool name, service (`users` / `billing`), arguments, result status, duration in ms.
- Do not log backend response bodies.

## Response Shape

- **Do not return a `status` boolean** in HTTP responses or tool results. Success/failure is carried by the HTTP status code or the MCP error flag.
- **Errors:** throw a domain exception with a plain string message (`throw new TenantNotFoundException('Tenant не знайдено');`). Do not construct array payloads like `['message' => '...', 'status' => false]`.

## Tests

- No real network calls in tests: backends and Google are mocked with `MockHttpClient` / `MockResponse`.
- Backend response fixtures live in `tests/Fixtures/Users/` and `tests/Fixtures/Billing/` and must match the services' OpenAPI specs (`public/openapi/internal-ai.yaml` in each service repo). When a backend contract changes, update the fixtures first.
- Every tool has tests for: correct URL and query, correct headers (service token, `X-Acting-User`, `traceparent`), argument validation, and `404` / `422` / `5xx` handling.

## Postman

- The Postman collection (`postman/mcp-server.postman_collection.json`, v2.1) and environment (`postman/local.postman_environment.json`) are part of the codebase and must stay in sync with it.
- Whenever a tool is added, renamed, or its parameters change, update the matching `tools/call` request, the `tools/list` test, and the Backends folder if the backend contract changed.
- Never commit real tokens, client secrets, or emails — only placeholders in `{{variables}}`. Personal values go into a local, git-ignored environment copy.
- Every request has Postman tests (status code, key fields, no PII keys). Requests that do not need interactive OAuth must pass in `newman`.

## Code Style

- Avoid excessively large methods. There is no strict line limit, but if a method becomes hard to read at a glance, extract smaller private methods or move logic to a dedicated service/helper.
- Avoid magic numbers and strings — use constants or enums (`src/Enums/`).
- Exceptions go in `src/Exception/`.
- Add comments only when needed — to explain a non-obvious *why* (hidden constraint, workaround, surprising behavior). Do not write docblocks that restate the method/class name or what the code already says. Type annotations for static analysis (`@param`, `@return`, `@var`) are fine. Tool descriptions for the model are not comments — keep them complete.
- Run PHP-CS-Fixer and PHPStan before finishing.
- Use LF as line separator
