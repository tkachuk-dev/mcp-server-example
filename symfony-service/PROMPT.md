# Задача: internal read-only API для AI/MCP у Symfony-сервісі (Users / Auth)

## Контекст
Система складається з трьох частин:
- **Symfony-сервіс (цей)** — сервіс користувачів: реєстрація, автентифікація,
  ролі й права, 2FA, скидання паролів, блокування акаунтів, журнал
  входів і подій безпеки. Він — джерело істини для userId.
- **Laravel-сервіс** — замовлення, платежі й підписки (білінг),
  аналітика клієнтів (RFM, churn). Посилається на userId з цього сервісу.
- **MCP-сервер на Symfony** — тонкий адаптер, через який команда питає
  AI про дані обох сервісів.

Цей промт — для Symfony-сервісу. Він віддає тільки дані про користувачів
і безпеку. Даних про замовлення й гроші тут немає і не має бути.

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
   (контролери-Action, форма DTO, репозиторії, OpenAPI, формат відповідей).
   Якщо промт суперечить CLAUDE.md — пріоритет у CLAUDE.md, а суперечність
   опиши в плані.
1. Досліди проєкт: версію Symfony/PHP, структуру контролерів, security.yaml
   (firewall'и, role_hierarchy), сутності User/Tenant, як зберігаються
   входи (login log, audit trail, LoginSuccessEvent/LoginFailureEvent),
   2FA, блокування й скидання паролів, Serializer, Monolog, RateLimiter,
   тести (WebTestCase/fixtures).
   Також: чи підключений Symfony Messenger з транспортом RabbitMQ і чи
   публікуються події user.* (в цій задачі їх НЕ додавай, тільки опиши
   стан у плані), чи є health-ендпоінти, обробка traceparent/X-Request-Id,
   OpenAPI-специфікації.
2. Знайди існуючі репозиторії/сервіси, які вже вміють рахувати ці дані.
   НЕ пиши нову логіку збору подій і НЕ пиши сирий SQL — використовуй
   наявні репозиторії (QueryBuilder/DQL допустимі для агрегатів). Якщо
   якихось даних (наприклад, журналу входів) у проєкті немає — зупинись
   і запитай, а не вигадуй.
3. Покажи план (файли, що створюєш/змінюєш) і чекай підтвердження.

## Вимоги

### Ендпоінти (тільки GET, префікс /internal/ai/v1)
- GET /internal/ai/v1/tenants/{tenantId}/users-summary — агрегати по
  користувачах tenant'а: усього, активних за 30 днів, заблокованих,
  з непідтвердженою поштою, розподіл за ролями, частка з увімкненою 2FA.
- GET /internal/ai/v1/tenants/{tenantId}/login-activity?days=N — агрегати
  по днях: успішні входи, невдалі входи, унікальні активні користувачі,
  скидання паролів; days 1–90, за замовчуванням 30.
- GET /internal/ai/v1/tenants/{tenantId}/security-events?limit=N — останні
  події безпеки (тип: failed_login_burst, account_locked, 2fa_disabled,
  password_reset, role_changed; внутрішній userId; час); limit 1–200,
  за замовчуванням 50.
- Один контролер-Action на маршрут (за CLAUDE.md), у
  src/Controller/Internal/Ai/: GetUsersSummaryController::getUsersSummary,
  GetLoginActivityController::getLoginActivity,
  GetSecurityEventsController::getSecurityEvents. Без __invoke.
- Query-параметри через Request DTO (#[MapQueryString]) з Assert\Range;
  межі й значення за замовчуванням — типізовані константи класу.
- Неіснуючий tenant → 404 (доменний виняток із src/Exception/).

### Дані
- Request/Response DTO в src/Dto/Ai/ у формі, описаній у CLAUDE.md
  (mutable, private nullable-властивості, setters/getters, без
  конструктора). Ніколи не серіалізувати сутності напряму.
- Агрегатні запити — тільки в репозиторіях (QueryBuilder).
- Ключі JSON у snake_case (через name converter), без поля status.
- Жодної PII і жодних секретів: без email, телефонів, імен, адрес, IP,
  user agent, хешів паролів, токенів, 2FA-секретів. Тільки внутрішні
  userId, типи подій, дати й агрегати.
- Дати в ISO 8601 (UTC).
- Важкі обчислення кешувати (cache.app, TTL 10 хв, ключ з tenantId і
  параметрів). security-events не кешувати довше ніж 1 хв.

### Автентифікація
- Новий stateless firewall `internal_ai` (pattern ^/internal/ai), розміщений
  ВИЩЕ за основний firewall.
- AccessTokenHandler App\Security\Internal\McpServiceTokenHandler:
    - env MCP_SERVICE_TOKEN_HASHES — JSON-масив sha256-хешів (для ротації);
    - порівняння через hash_equals;
    - обов'язковий заголовок X-Acting-User з валідним email, інакше 401;
    - користувач InMemoryUser з id "mcp:<email>" і єдиною роллю ROLE_AI_READ.
- Service token не має нічого спільного зі звичайними токенами
  користувачів цього сервісу (JWT/сесії) і не може бути використаний
  на інших маршрутах.
- access_control: ^/internal/ai → ROLE_AI_READ тільки для GET; будь-який
  інший метод заборонений.
- ROLE_AI_READ не повинна входити в role_hierarchy інших ролей і навпаки.
- Запити від MCP не повинні потрапляти в журнал входів користувачів
  і не повинні оновлювати last_login/last_seen.

### Аудит і ліміти
- Monolog-канал ai_audit, JSON у stderr.
- Listener: на REQUEST (після firewall) — rate limiter internal_ai
  (sliding_window, 120/хв, ключ = actor) → 429; на RESPONSE — лог
  actor, path, query, status, тривалість у мс.

### Мікросервісні вимоги
- Дані для internal API — тільки з власної БД сервісу; жодних
  синхронних викликів до Billing-сервісу під час обробки цих запитів.
- GET /health/live (процес живий) і GET /health/ready (БД, кеш, брокер
  доступні) — без автентифікації, поза /internal/ai, без деталей
  помилок у відповіді. Якщо такі ендпоінти вже є — використовуй їх.
- Kernel listener для трасування: прийняти traceparent і X-Request-Id
  або згенерувати, додати в усі логи (Monolog processor, зокрема в
  ai_audit), повернути X-Request-Id у відповіді.
- OpenAPI за правилами CLAUDE.md: новий файл public/openapi/internal-ai.yaml,
  підключений з public/openapi/openapi.yaml; схеми й помилки — у
  components.yaml через $ref. Для цих ендпоінтів окрема security scheme
  serviceToken (Bearer) + параметр X-Acting-User, а не bearerAuth (JWT).
- Сервіс stateless (жодних локальних файлів/сесій для /internal/ai),
  конфігурація тільки через env — щоб його можна було масштабувати
  горизонтально.

### Мережа
- Якщо в репо є конфіг Caddy/FrankenPHP для публічного входу — додай
  блокування /internal/* (404). Якщо інфраструктура в іншому репо —
  напиши, що треба змінити, в підсумку.

### Тести (WebTestCase)
- без токена → 401; невалідний токен → 401; без X-Acting-User → 401;
- звичайний токен/сесія користувача на /internal/ai → 401;
- POST з валідним токеном → 403/405;
- неіснуючий tenant → 404; days=1000 і limit=1000 → помилка валідації;
- у JSON-відповідях немає ключів email/phone/name/firstName/lastName/
  ip/userAgent/password/token;
- запит від MCP не змінює last_login і не пише в журнал входів;
- успішні відповіді всіх трьох ендпоінтів мають очікувану структуру
  і відповідають OpenAPI-специфікації;
- X-Request-Id з запиту повертається у відповіді, без нього — генерується;
- /health/live → 200 без токена.

## Обмеження
- Не змінюй поведінку існуючих firewall'ів, маршрутів і логіки
  автентифікації користувачів.
- Не додавай нові залежності без потреби; якщо потрібні — спитай.
- Дотримуйся code style проєкту, запусти наявні лінтери/PHPStan і тести.

## Наприкінці
Короткий підсумок: створені/змінені файли, нові env-змінні, команди
для генерації токена й хешу, шлях до OpenAPI-специфікації, стан
публікації подій user.*, що треба зробити в інфраструктурі.
