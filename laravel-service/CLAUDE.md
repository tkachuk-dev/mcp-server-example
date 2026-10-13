# CLAUDE.md — Billing Service (Laravel) Guidelines

## General Principles

- **KISS**: Keep It Simple. Prefer straightforward solutions to clever ones.
- Methods must be short and focused — one responsibility per method.
- Avoid long argument lists. If a method needs more than 3–4 arguments, group them into a DTO or value object.

## Controllers

- **One route per controller.** Every controller is an *Action*: a single class with a single public method bound to a single route. Do not group multiple routes on one controller class and do not use `Route::resource()` / `Route::apiResource()`.
- Name the class after the action, not the resource: `GetOrderController`, `RefundPaymentController`, `CancelSubscriptionController` — not `OrderController` with many methods.
- Name the action method after the action itself (usually matching the route name), e.g. `getOrder`, `refundPayment`, `cancelSubscription`, and register it explicitly: `Route::get('/orders/{order}', [GetOrderController::class, 'getOrder'])->name('getOrder');`. **Do not use `__invoke`** — a named method reads better in stack traces, IDE navigation, and profilers.
- Group related actions by directory (namespace), not by class: `app/Http/Controllers/Billing/Order/GetOrderController.php`, `app/Http/Controllers/Billing/Order/CancelOrderController.php`, etc.
- Keep the action thin: validate input via a Form Request, call a service, return an API Resource. Business logic belongs in services (`app/Services/`), not in controllers, models, or Form Requests.
- **Do not add `->whereNumber()` / `->where('id', '[0-9]+')` on route parameters** that are resolved via route model binding or typed `int` — a missing or non-matching model already produces a 404. Only add constraints when you need a pattern beyond the type (a custom slug, a specific format, etc.).

## Requests & DTOs

- Input validation lives only in Form Requests (`app/Http/Requests/`). Do not call `$request->validate()` or `Validator::make()` in controllers.
- Form Requests contain only `rules()`, `authorize()`, and, if needed, `messages()` / `prepareForValidation()`. Limits and magic values are typed class constants: `public const int MAX_LIMIT = 200;`.
- After validation, convert input into a DTO via a method on the Form Request (`toDto(): GetChurnRiskDto`). Services accept DTOs, never `Request` / `FormRequest` / raw arrays.
- DTOs are only allowed inside `app/Dto/`. Do not create DTO-like classes in other directories.
- DTOs are `final readonly` classes with constructor promotion and non-nullable typed properties (the Form Request has already validated the input).

## Responses (API Resources)

- Responses are built only via API Resources (`app/Http/Resources/`). Never return Eloquent models, collections, or `toArray()` directly from a controller.
- Resources explicitly list every field — never `return parent::toArray($request);` — so new model columns cannot leak into the API.
- JSON keys use **snake_case**.
- Disable the `data` wrapper globally (`JsonResource::withoutWrapping()`) unless an endpoint is paginated.

## Eloquent & Queries

- **Never** build queries (`Model::where()`, `DB::table()`, `->get()` on a builder) outside a Repository class (`app/Repositories/`).
- The only exception is `Model::find($id)` / `findOrFail($id)` and route model binding, which may be used anywhere.
- Inside a Repository, prefer simple Eloquent calls; use the query builder with joins/aggregates only when the query needs them. Reusable conditions go into local query scopes on the model.
- Do not write raw SQL (`DB::raw`, `DB::select`) unless there is no builder equivalent; if you must, explain why in a comment.
- Always eager-load relations used by Resources (`with()` / `load()`) to avoid N+1; `Model::preventLazyLoading()` is enabled outside production.

## Laravel Configuration

- Call `env()` only inside `config/*.php`. Everywhere else use `config()`.
- Rely on autowiring; bind in a service provider only for interfaces or non-trivial construction.
- Middleware, rate limiters, exception rendering, and route files are registered in `bootstrap/app.php`.
- Events and listeners: rely on event discovery (listener with a typed `handle(EventClass $event)` method); do not register them manually unless discovery cannot find them.
- Queue jobs and event consumers must be idempotent — the same message may be delivered more than once.

## Entity IDs

- Model IDs are currently **autoincrement integers**.
- Migration to **UUIDv7** is planned. UUIDv7 is time-sorted, so cursor-based pagination using `> :lastId` with `ORDER BY id ASC` will remain valid after migration.
- When touching code that assumes integer IDs (e.g., `$lastId = 0`), leave it as-is until the migration is done — do not switch to offset-based pagination or add UUID handling prematurely.
- `user_id` in this service is the ID issued by the Users service. It is never a foreign key to a local `users` table owned by this service — only to the local user projection updated from `user.*` events.

## Money

- Amounts are stored and returned as integers in minor units (cents) plus an ISO 4217 currency code. Never use floats for money.

## Response Shape

- **Do not return a `status` boolean in responses.** Neither success responses (`{"status": true, ...}`) nor error payloads (`{"status": false, "message": "..."}`) should include it. HTTP status codes already carry that information — 2xx means success, 4xx/5xx means failure. Return the resource on success, and the plain error message on failure.
- **Errors:** throw a domain exception from `app/Exceptions/` with a plain string message (`throw new OrderNotFoundException('Order not found');`) and map it to an HTTP status in `bootstrap/app.php` (`withExceptions`). Do not build `response()->json(['message' => ..., 'status' => false])` by hand.
- Validation errors use Laravel's default 422 format.
- **Legacy endpoints** may still emit `status`. When touching one, drop it; don't add it to anything new.

## OpenAPI

- **Every new or updated endpoint must be documented in OpenAPI.** Whenever a route is added, changed, or removed, update the corresponding spec so the docs stay in sync with the code.
- Spec files live in `public/openapi/`. The entry point is `public/openapi/openapi.yaml`; per-tag spec files (`order.yaml`, `payment.yaml`, `subscription.yaml`, `internal-ai.yaml`, …) are referenced from it and hold the paths for that tag. Shared components (schemas, security schemes, enums, error responses) live in `public/openapi/components.yaml`.
- Use **OpenAPI 3.1**. Public authenticated endpoints reference the shared `bearerAuth` security scheme; internal service-to-service endpoints under `/internal/*` use the `serviceToken` scheme (Bearer service token + `X-Acting-User` header).
- Match the Form Request / Resource shape exactly: property names, nullability, enum values, and validation constraints (`minimum`, `maximum`, `minLength`, `maxLength`, `format`, etc.).
- Reuse component schemas via `$ref` — do not inline the same shape twice.
- Do not include `status` booleans in responses (see Response Shape above). Errors use the shared error schema.

## Tests

- Feature tests for every endpoint (happy path, validation, auth, 404); unit tests for services with non-trivial logic.
- Use factories for test data; do not depend on seeders.
- Assert response shape with `assertJsonStructure()` / `assertExactJson()` where it matters.

## Code Style

- Avoid excessively large methods. There is no strict line limit, but if a method becomes hard to read at a glance, extract smaller private methods or move logic to a dedicated service/helper.
- Avoid magic numbers and strings — use constants or enums (`app/Enums/`).
- Exceptions go in `app/Exceptions/`.
- Add comments only when needed — to explain a non-obvious *why* (hidden constraint, workaround, surprising behavior). Do not write docblocks that restate the method/class name or what the code already says. Type annotations for static analysis (`@param`, `@return`, `@var`) are fine.
- Run Pint and Larastan before finishing.
- Use LF as line separator
