# Задача: internal read-only API для AI/MCP у Laravel-сервісі (Billing / Orders)

## Контекст
Система складається з трьох частин:
- **Symfony-сервіс** — сервіс користувачів: реєстрація, автентифікація,
  ролі, 2FA, журнал входів. Він — джерело істини для userId.
- **Laravel-сервіс (цей)** — замовлення, платежі, повернення й підписки
  (білінг), а також аналітика клієнтів на їх основі: RFM-сегментація,
  прогноз відтоку (churn), виручка. Клієнт тут ідентифікується userId
  з Symfony-сервісу.
- **MCP-сервер на Symfony** — тонкий адаптер, через який команда питає
  AI про дані обох сервісів.

Цей промт — для Laravel-сервісу. Він віддає тільки комерційні дані
(замовлення, гроші, підписки, сегменти клієнтів). Даних про
автентифікацію тут немає і не має бути.

## Архітектура (мікросервіси)
- Кожен сервіс — окремий мікросервіс: власний репозиторій, власна БД
  (database per service), окремий контейнер, незалежний деплой і
  масштабування.
- Жоден сервіс не читає БД іншого сервісу. Взаємодія тільки так:
    - синхронно — internal HTTP API (/internal/ai/v1/...), доступний лише
      в приватній мережі кластера;
    - асинхронно — доменні події через брокер повідомлень (RabbitMQ):
      Users-сервіс публікує user.registered, user.blocked, user.deleted,
      user.role_changed; Billing-сервіс їх споживає і тримає локальну
      проєкцію користувачів (userId, tenantId, статус).
- Публічний трафік іде через API Gateway / ingress; /internal/* назовні
  не публікується. Сервіси знаходять один одного за DNS-іменами
  (users-service, billing-service, mcp-server), а не за IP.
- MCP-сервер — окремий мікросервіс (BFF для AI) без власної БД, тільки
  читає internal API.
- Наскрізне трасування: кожен запит несе traceparent (W3C Trace Context)
  і X-Request-Id; сервіси передають їх далі й пишуть у кожен рядок логу.
- Контракт internal API версіонується в шляху (/internal/ai/v1/...) і
  описаний в OpenAPI-специфікації в репозиторії сервісу-власника.
  Несумісні зміни → v2; v1 живе, поки MCP-сервер не мігрує.

Спільний для обох сервісів контракт: префікс /internal/ai/v1, тільки GET,
заголовки Authorization: Bearer <service token> і X-Acting-User: <email>,
однакові коди помилок (401/403/404/405/422/429).

## Перед кодом
0. Прочитай CLAUDE.md у корені проєкту і дотримуйся його правил
   (контролери-Action, Form Request → DTO, репозиторії, API Resources,
   гроші, OpenAPI, формат відповідей). Якщо промт суперечить CLAUDE.md —
   пріоритет у CLAUDE.md, а суперечність опиши в плані.
1. Досліди проєкт: версію Laravel/PHP, структуру роутів (routes/*.php,
   bootstrap/app.php), middleware, guards у config/auth.php, Gate/Policy,
   моделі Order/Payment/Refund/Subscription/Tenant, наявні сервіси
   аналітики (RFM-сегментація, churn prediction, звіти по виручці),
   API Resources, config/logging.php, RateLimiter, тести (PHPUnit або
   Pest, factories/seeders).
   Також: чи є consumer подій user.* з RabbitMQ і локальна проєкція
   користувачів (таблиця/модель), чи є health-ендпоінти, обробка
   traceparent/X-Request-Id, OpenAPI-специфікації.
2. Знайди існуючі сервіси, які вже рахують RFM, churn і виручку. НЕ пиши
   нову аналітику і НЕ пиши сирий SQL — використовуй наявні сервіси
   (Eloquent/Query Builder для агрегатів допустимі). Якщо чогось немає,
   зупинись і запитай.
3. Покажи план (файли, що створюєш/змінюєш) і чекай підтвердження.

## Вимоги

### Ендпоінти (тільки GET, префікс /internal/ai/v1)
- Окремий файл роутів routes/internal_ai.php, підключений у bootstrap/app.php
  (або RouteServiceProvider — залежно від версії) з префіксом internal/ai/v1,
  БЕЗ групи middleware `web` (без сесій, cookies, CSRF).
- GET /internal/ai/v1/tenants/{tenantId}/rfm-summary — агрегати по
  RFM-сегментах: кількість клієнтів, середні recency/frequency/monetary.
- GET /internal/ai/v1/tenants/{tenantId}/churn-risk?limit=N — топ клієнтів
  за ризиком відтоку (userId, score, сегмент, дата останнього замовлення);
  limit 1–200, за замовчуванням 50.
- GET /internal/ai/v1/tenants/{tenantId}/revenue-summary?days=N — виручка
  по днях: сума, кількість замовлень, середній чек, повернення, нові й
  скасовані підписки; days 1–365, за замовчуванням 30. Суми в мінімальних
  одиницях (копійки/центи) + код валюти.
- Один контролер-Action на маршрут (за CLAUDE.md), у
  app/Http/Controllers/Internal/Ai/: GetRfmSummaryController::getRfmSummary,
  GetChurnRiskController::getChurnRisk,
  GetRevenueSummaryController::getRevenueSummary. Без __invoke і без
  Route::resource().
- Валідація параметрів через Form Request (integer|min|max, межі й
  значення за замовчуванням — типізовані константи), далі toDto() →
  DTO в app/Dto/Ai/ → сервіс. Помилка валідації → 422 JSON.
- Неіснуючий tenant → 404 (findOrFail / route model binding або доменний
  виняток з app/Exceptions/).

### Дані
- Окремі API Resources в app/Http/Resources/Ai/ з явним переліком полів.
  Ніколи не віддавати Eloquent-моделі чи parent::toArray() напряму.
- Агрегатні запити — тільки в репозиторіях (app/Repositories/).
- Ключі JSON у snake_case, без обгортки data, без поля status.
- Жодної PII і платіжних даних: без email, телефонів, імен, адрес
  доставки, номерів карток, IBAN, ID транзакцій платіжного провайдера.
  Тільки внутрішні userId (з Symfony-сервісу), ID замовлень, агрегати.
- Дати в ISO 8601 (UTC).
- Важкі обчислення кешувати (Cache::remember, TTL 10 хв, ключ з tenantId
  і параметрів).

### Автентифікація
- Middleware App\Http\Middleware\AuthenticateMcpService на всю групу
  internal/ai (не Sanctum, не сесії):
    - env MCP_SERVICE_TOKEN_HASHES — JSON-масив sha256-хешів (для ротації),
      читати через config/, а не env() у коді;
    - Bearer-токен порівнювати через hash_equals;
    - обов'язковий заголовок X-Acting-User з валідним email, інакше 401;
    - actor "mcp:<email>" класти в атрибути запиту для аудиту й лімітів.
- Тільки GET: будь-який інший метод → 405 (роути оголошені лише як GET).
- Цей доступ не повинен давати жодних прав у звичайних guard'ах/Gate
  застосунку, і звичайні користувачі не повинні отримати доступ до
  /internal/ai.

### Аудит і ліміти
- Лог-канал ai_audit у config/logging.php: driver monolog, StreamHandler
  на php://stderr, JsonFormatter.
- RateLimiter::for('internal_ai') — 120/хв, ключ = actor → 429
  (middleware throttle:internal_ai після автентифікації).
- Terminable middleware (або after-middleware): лог actor, path, query,
  status, тривалість у мс.

### Мікросервісні вимоги
- Дані для internal API — тільки з власної БД сервісу; жодних
  синхронних викликів до Users-сервісу під час обробки цих запитів.
  Якщо потрібен статус користувача (наприклад, виключити заблокованих
  з churn-risk) — бери його з локальної проєкції, яку оновлюють події
  user.*. Якщо проєкції чи consumer'а немає — НЕ реалізовуй їх у цій
  задачі, а запитай.
- GET /health/live (процес живий) і GET /health/ready (БД, кеш, черга
  доступні) — без автентифікації, поза /internal/ai, без деталей
  помилок у відповіді. Якщо такі ендпоінти вже є (наприклад, /up) —
  використовуй їх.
- Middleware для трасування: прийняти traceparent і X-Request-Id або
  згенерувати, додати в контекст усіх логів (Log::withContext /
  Context), повернути X-Request-Id у відповіді.
- OpenAPI за правилами CLAUDE.md: public/openapi/internal-ai.yaml,
  підключений з public/openapi/openapi.yaml; схеми й помилки — у
  components.yaml через $ref; security scheme serviceToken + параметр
  X-Acting-User. Формат JSON має збігатися зі стилем Symfony-сервісу
  (snake_case, ті самі назви полів для дат і помилок).
- Сервіс stateless для /internal/ai (кеш і ліміти в Redis, а не в
  file/array драйвері), конфігурація тільки через env — щоб його можна
  було масштабувати горизонтально.

### Мережа
- Якщо в репо є конфіг nginx/Caddy/Octane для публічного входу — додай
  блокування /internal/* (404). Якщо інфраструктура в іншому репо —
  напиши, що треба змінити, в підсумку.

### Тести (Feature tests)
- без токена → 401; невалідний токен → 401; без X-Acting-User → 401;
- POST з валідним токеном → 405;
- неіснуючий tenant → 404; limit=1000 і days=1000 → 422;
- у JSON-відповідях немає ключів email/phone/name/first_name/last_name/
  firstName/lastName/address/card/iban;
- успішні відповіді всіх трьох ендпоінтів мають очікувану структуру
  (assertJsonStructure);
- перевищення ліміту → 429;
- відповіді відповідають OpenAPI-специфікації;
- X-Request-Id з запиту повертається у відповіді, без нього — генерується;
- /health/live → 200 без токена.

## Обмеження
- Не змінюй поведінку існуючих guard'ів, middleware-груп і маршрутів.
- Не додавай нові залежності без потреби; якщо потрібні — спитай.
- Дотримуйся code style проєкту, запусти наявні лінтери (Pint),
  Larastan/PHPStan і тести.

## Наприкінці
Короткий підсумок: створені/змінені файли, нові env-змінні, команди
для генерації токена й хешу, шлях до OpenAPI-специфікації, стан
consumer'а подій user.* і локальної проєкції, що треба зробити в
інфраструктурі.
