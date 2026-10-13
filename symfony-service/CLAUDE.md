# CLAUDE.md — Users Service (Symfony) Guidelines

## General Principles

- **KISS**: Keep It Simple. Prefer straightforward solutions to clever ones.
- Methods must be short and focused — one responsibility per method.
- Avoid long argument lists. If a method needs more than 3–4 arguments, group them into a DTO or value object.

## Controllers

- **One route per controller.** Every controller is an *Action*: a single class with a single public method carrying a single `#[Route]`. Do not group multiple routes on one controller class.
- Name the class after the action, not the resource: `GetUserController`, `BlockUserController`, `ResetPasswordController` — not `UserController` with many methods.
- Name the action method after the action itself (usually matching the route `name`), e.g. `getUser`, `blockUserById`, `resetPassword`. **Do not use `__invoke`** — a named method reads better in stack traces, IDE navigation, and profilers.
- Group related actions by directory (namespace), not by class. `src/Controller/Admin/User/GetUserController.php`, `src/Controller/Admin/User/BlockUserController.php`, `src/Controller/Auth/LoginController.php`, etc.
- Keep the action thin: parse input (DTO via `#[MapRequestPayload]` / `#[MapQueryString]`), call a service, return a Response DTO. Business logic belongs in services, not in controllers.
- **Do not add `requirements: ['id' => '\d+']` (or similar numeric regex) on int-typed route parameters.** Symfony's argument resolver already returns 404 when a route parameter cannot be coerced into the controller's declared scalar type (e.g. `int $id`), and the service throws for a valid-but-missing entity. The regex is redundant. Only add `requirements` when you actually need to constrain the pattern beyond the parameter's type (a custom slug, a specific format, etc.).

## DTOs

- DTOs are only allowed inside the `src/Dto/` directory.
- Do not create DTO-like classes in other directories (Controllers, Services, etc.).

### Request DTOs

Request DTOs are the target of `#[MapRequestPayload]` / `#[MapQueryString]` — the Symfony denormalizer populates them from the incoming request, then the validator runs. Follow this shape:

- **Non-`final`, non-`readonly` class.** The denormalizer sets properties (via setters), so the class must be mutable.
- **Private nullable properties initialized to `null`.** One property per input field:
  ```php
  #[Assert\NotBlank]
  #[Assert\Email]
  private ?string $email = null;
  ```
  Nullable + null default lets Symfony populate the DTO even when the field is missing, so the validator can produce a proper violation instead of a hard TypeError. Keep the property `private` — consumers go through the getter.
- **Validation attributes on properties**, not on getters or constructor params.
- **Setters accept nullable, getters return non-nullable.** Getters do not null-check — they trust the validator ran and passed:
  ```php
  public function setEmail(?string $email): void { $this->email = $email; }
  public function getEmail(): string { return $this->email; }
  ```
- **Consumers must call getters** — properties are `private`, so there is no way to reach them directly. This enforces the "non-null after validation" contract.
- **No constructor.** Do not use constructor promotion for Request DTOs (blocks the denormalizer's default population path and hides validation attributes).
- **Class constants for limits and magic values** (typed constants where possible): `public const int MAX_PASSWORD_LENGTH = 4096;`
- **Do not add `#[SerializedName]` for snake_case ↔ camelCase mappings.** `services.yaml` configures `MetadataAwareNameConverter` with a `CamelCaseToSnakeCase` fallback, so a JSON key `first_name` maps to property `firstName` automatically. Only add `#[SerializedName]` when the JSON key does not follow snake_case (e.g. mixed case `oAuth_providerId`, camelCase `avatarUrl`, `twoFactorCode`) or when you need to preserve a specific casing on serialization.

### Response DTOs

Response DTOs follow the **same mutable shape** as Request DTOs — non-`final`, non-`readonly`, no constructor, private nullable properties initialized to `null`, populated via setters, exposed via getters. Build them with a static factory such as `fromEntity(User $user): self` that calls the setters if no logic needed, otherwise - use mapper. Do **not** use `final readonly` with constructor promotion for Response DTOs.

## Repositories & Magic Finder Methods

- **Never** use `findOneBy()` or `findBy()` outside of a Repository class.
- The only exception is `find($id)`, which may be used anywhere.
- Inside a Repository class, prefer `findOneBy()` and `findBy()` for simple queries that don't require complex logic. Use QueryBuilder only when the query involves joins, conditions, aggregations, or other non-trivial logic.
- All custom query logic must live in the appropriate Repository.

## Symfony Configuration

- Use PHP attributes for service configuration instead of `services.yaml` (e.g., `#[Autowire]`, `#[AutoconfigureTag]`, `#[AsEventListener]`, `#[AsMessageHandler]`, etc.).
- Do not register services, tags, or bindings manually in `services.yaml` when an attribute exists for the same purpose.
- Always use `#[AutowireIterator]` for tagged iterators. Never use `#[TaggedIterator]` — it is a deprecated alias.

## Entity IDs

- Entity IDs are currently **autoincrement integers**.
- Migration to **UUIDv7** is planned. UUIDv7 is time-sorted, so cursor-based pagination using `> :lastId` with `ORDER BY id ASC` will remain valid after migration.
- User IDs are consumed by other services (Billing, MCP) via events and the internal API. Any change to the ID format must go through a new contract version (`/internal/ai/v2`, new event schema), never in place.
- When touching code that assumes integer IDs (e.g., `$lastId = 0`), leave it as-is until the migration is done — do not switch to offset-based pagination or add UUID handling prematurely.

## OpenSearch Listings

Applies to the admin user search index (`users`).

- **All field names in OpenSearch documents, index mappings, and filterable-field keys use `snake_case`.** This covers top-level keys, nested-object keys (e.g. `profile.full_name`, `tenant.name`, `last_login.created_at`), the keys returned by `getFilterableFields()`, and the internal field paths in their values (e.g. `['full_name.ngram^3']`).
- Do **not** mix camelCase into the index — the OpenSearch document shape is its own contract, not derived from PHP property names or Symfony's name converter (arrays bypass it).
- SQL column aliases in `LOAD_SQL` already come back snake_case; map them into snake_case document keys directly in `rowToDocument()` without a camelCase intermediate step.

## Response Shape

- **Do not return a `status` boolean in responses.** Neither success responses (`{"status": true, ...}`) nor error payloads (`{"status": false, "message": "..."}`) should include it. HTTP status codes already carry that information — 2xx means success, 4xx/5xx means failure. Return the resource DTO on success, and the plain error message on failure.
- **Errors:** throw a domain exception with a plain string message (`throw new UserNotFoundException('User not found');`). Do not construct array payloads like `['message' => '...', 'status' => false]`.
- **Legacy endpoints** may still emit `status`. When touching one, drop it; don't add it to anything new.

## OpenAPI

- **Every new or updated endpoint must be documented in OpenAPI.** Whenever a route is added, changed, or removed, update the corresponding spec so the docs stay in sync with the code.
- Spec files live in `public/openapi/`. The entry point is `public/openapi/openapi.yaml`; per-tag spec files (`auth.yaml`, `user.yaml`, `role.yaml`, `two-factor.yaml`, `password-reset.yaml`, `session.yaml`, `tenant.yaml`, `internal-ai.yaml`, …) are referenced from it and hold the paths for that tag. Shared components (schemas, security schemes, enums, error responses) live in `public/openapi/components.yaml`.
- Use **OpenAPI 3.1**. Authenticated endpoints must reference the shared `bearerAuth` (JWT) security scheme; only the login/refresh/register/password-reset-request/public endpoints are `security: []`.
- Exception: internal service-to-service endpoints under `/internal/*` are documented in `public/openapi/internal-ai.yaml` and use the `serviceToken` security scheme (Bearer service token + `X-Acting-User` header), never `bearerAuth`.
- Match the request/response DTO shape exactly: property names, nullability, enum values (use the integer values for `int`-backed enums like `Role`, `UserStatus`, `VerificationStatus`, `TwoFactorMethod`, `LoginResult`; string values for string-backed enums like `SecurityEventType`, `SortOrderType`), and validation constraints (`minLength`, `maxLength`, `minimum`, `maximum`, `format: email`, `format: uri`, etc.).
- Reuse component schemas via `$ref` — do not inline the same shape twice.
- Do not include `status` booleans in responses (see Response Shape above). Errors use the shared error schema.

## Code Style

- Avoid excessively large methods. There is no strict line limit, but if a method becomes hard to read at a glance, extract smaller private methods or move logic to a dedicated service/helper.
- Avoid magic numbers and strings — use constants or enums (`src/Enums/`).
- Exceptions go in `src/Exception/`.
- Add comments only when needed — to explain a non-obvious *why* (hidden constraint, workaround, surprising behavior). Do not write docblocks that restate the method/class name or what the code already says. Type annotations for static analysis (`@param`, `@return`, `@var`) are fine.
- Use LF as line separator