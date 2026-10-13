# Задача: MCP-сервер для внутрішньої команди

## Контекст
Новий окремий Symfony-проєкт на FrankenPHP. Це тонкий адаптер: тулзи MCP →
HTTP GET до internal API двох бекенд-сервісів:
- **Users-сервіс (Symfony)** — користувачі, автентифікація, ролі, 2FA,
  журнал входів і події безпеки. Джерело істини для userId.
- **Billing-сервіс (Laravel)** — замовлення, платежі, підписки, виручка,
  RFM-сегментація й прогноз відтоку клієнтів. Клієнти ідентифікуються
  тим самим userId, тому дані двох сервісів можна зіставляти.

Без БД, без Doctrine, без бізнес-логіки. Доступ для команди через інтернет
без VPN, логін через Google Workspace (OAuth).

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
- MCP-сервер (цей) — окремий мікросервіс (BFF для AI) без власної БД,
  тільки читає internal API. Брокер повідомлень йому не потрібен.
- Наскрізне трасування: кожен запит несе traceparent (W3C Trace Context)
  і X-Request-Id; сервіси передають їх далі й пишуть у кожен рядок логу.
- Контракт internal API версіонується в шляху (/internal/ai/v1/...) і
  описаний в OpenAPI-специфікації в репозиторії сервісу-власника.
  Несумісні зміни → v2; v1 живе, поки MCP-сервер не мігрує.

Спільний контракт internal API (вже існує в обох сервісах):
- префікс /internal/ai/v1, тільки GET;
- заголовки: Authorization: Bearer <service token>, X-Acting-User: <email>;
- коди: 401/403/404/405/422/429;
- у кожного сервісу свій base URL і свій service token.

Users-сервіс:
- GET /internal/ai/v1/tenants/{tenantId}/users-summary
- GET /internal/ai/v1/tenants/{tenantId}/login-activity?days=N (1–90)
- GET /internal/ai/v1/tenants/{tenantId}/security-events?limit=N (1–200)

Billing-сервіс:
- GET /internal/ai/v1/tenants/{tenantId}/rfm-summary
- GET /internal/ai/v1/tenants/{tenantId}/churn-risk?limit=N (1–200)
- GET /internal/ai/v1/tenants/{tenantId}/revenue-summary?days=N (1–365)

## Перед кодом
0. Прочитай CLAUDE.md у корені проєкту і дотримуйся його правил
   (межі сервісу, тулзи, HTTP-клієнти, worker mode, тести). Якщо промт
   суперечить CLAUDE.md — пріоритет у CLAUDE.md, а суперечність опиши
   в плані.
1. Встанови symfony/mcp-bundle (з офіційним mcp/sdk). Бандл
   експериментальний — НЕ покладайся на пам'ять: прочитай його код і README
   у vendor/, перевір назви атрибутів (#[McpTool]), конфіг (mcp.yaml),
   тип маршруту, роботу HTTP-транспорту і сесій.
2. Покажи план і чекай підтвердження.

## Вимоги

### Стек
- Актуальна стабільна Symfony, PHP 8.3+, FrankenPHP у worker mode.
- symfony/mcp-bundle (тільки HTTP-транспорт, stdio вимкнено),
  symfony/http-client, symfony/security-bundle, symfony/rate-limiter,
  monolog. Без Doctrine.

### HTTP-клієнти
- Два scoped HTTP-клієнти:
    - users_service.api — base_uri і Bearer token з env USERS_SERVICE_*;
    - billing_service.api — base_uri і Bearer token з env BILLING_SERVICE_*.
- Для обох: timeout 5, max_duration 10.
- Спільний клієнт-обгортка (один клас, два сервіси через DI), щоб логіка
  заголовків і обробки помилок не дублювалась.
- X-Acting-User = email автентифікованого користувача.
- 404 від бекенду → зрозуміла помилка "Tenant не знайдено"; 422 →
  "Невірні параметри" з назвою параметра; інші ≥400 і мережеві помилки →
  загальна помилка без внутрішніх деталей.
- Base URL сервісів — внутрішні DNS-імена (http://users-service,
  http://billing-service), тільки з env, без захардкоджених адрес.
- RetryableHttpClient: 1 повтор з backoff тільки на мережеві помилки
  і 502/503/504 (GET ідемпотентні); на 4xx — без повторів.
- Часткова відмова: якщо один сервіс недоступний, тулзи іншого працюють;
  модель отримує зрозуміле "Сервіс users/billing тимчасово недоступний".
- Трасування: для кожного tools/call генерувати traceparent і
  X-Request-Id, передавати в обидва сервіси, писати в лог.

### Тулзи
- src/Mcp/Tool/UsersTools.php:
    - users_get_summary(int tenantId)
    - users_get_login_activity(int tenantId, int days = 30)
    - users_get_security_events(int tenantId, int limit = 50)
- src/Mcp/Tool/BillingTools.php:
    - billing_get_rfm_summary(int tenantId)
    - billing_get_churn_risk(int tenantId, int limit = 50)
    - billing_get_revenue_summary(int tenantId, int days = 30)
- Чіткі описи для моделі: який сервіс і яка предметна область, що
  повертає, коли використовувати (наприклад, "питання про входи, блокування,
  2FA → users_*; про гроші, замовлення, відтік → billing_*"), що PII немає,
  що userId однаковий в обох сервісах і їх можна зіставляти.
- Діапазони параметрів перевіряти ще в MCP (до запиту до бекенду).

### Автентифікація (Google Workspace)
- Stateless firewall на маршрут MCP.
- AccessTokenHandler GoogleTokenHandler:
    - перевіряє access token через https://oauth2.googleapis.com/tokeninfo;
    - ОБОВ'ЯЗКОВО: aud === GOOGLE_CLIENT_ID (інакше приймемо токен,
      виданий іншому застосунку);
    - email_verified === "true" і email закінчується на @ALLOWED_EMAIL_DOMAIN;
    - кешує результат 5 хв за sha256 токена;
    - користувач InMemoryUser(email, ['ROLE_USER']).
- GET /.well-known/oauth-protected-resource:
  {"resource": "<MCP_PUBLIC_URL>", "authorization_servers": ["https://accounts.google.com"]}
- Entry point: 401 з
  WWW-Authenticate: Bearer resource_metadata="<url metadata>".
- Перевірка заголовка Origin (захист від DNS rebinding): якщо Origin є
  і не в allowlist з env — 403.

### Аудит і ліміти
- Rate limiter на користувача (60/хв) → 429.
- JSON-лог у stderr для кожного tools/call: email, назва тулзи, сервіс
  (users/billing), аргументи, статус, тривалість.

### FrankenPHP
- Сервіси зі станом реалізують ResetInterface; жодного стану в static.
- Сесії MCP: перевір, де бандл їх зберігає; налаштуй так, щоб робота
  з однією реплікою була коректною, і опиши в README, що потрібно для
  кількох реплік.

### Інфраструктура
- Dockerfile на офіційному образі FrankenPHP, worker mode.
- GET /health/live і GET /health/ready (ready не залежить від доступності
  бекендів — інакше падіння одного сервісу вимкне весь MCP).
- compose.yaml для локального запуску: MCP-сервер у спільній docker-мережі
  з users-service і billing-service (external network), або з
  WireMock/stub-контейнерами, що віддають відповіді за OpenAPI-специфікаціями
  сервісів.
- .env.example: USERS_SERVICE_INTERNAL_API, USERS_SERVICE_TOKEN,
  BILLING_SERVICE_INTERNAL_API, BILLING_SERVICE_TOKEN, GOOGLE_CLIENT_ID,
  ALLOWED_EMAIL_DOMAIN, MCP_PUBLIC_URL, MCP_ALLOWED_ORIGINS.

### Тести
- Тулзи обох сервісів з MockHttpClient: правильний base URL і токен
  для кожного сервісу, правильні URL і query, заголовки, обробка
  404/422/500, валідація діапазонів до запиту, повтор на 503 і його
  відсутність на 4xx, передача traceparent/X-Request-Id.
- Часткова відмова: billing недоступний → тулзи users працюють.
- Фікстури відповідей узгоджені з OpenAPI-специфікаціями обох сервісів
  (contract tests).
- GoogleTokenHandler з MockHttpClient: невірний aud, чужий домен,
  email_verified=false, валідний токен.
- Запит без токена → 401 з правильним WWW-Authenticate.
- Metadata-ендпоінт повертає правильний JSON.

### Postman
- Колекція postman/mcp-server.postman_collection.json (формат v2.1) і
  оточення postman/local.postman_environment.json. Без реальних токенів
  і секретів — тільки плейсхолдери.
- Змінні оточення: base_url, mcp_path, google_access_token, mcp_session_id,
  mcp_protocol_version, tenant_id, а для папки Backends —
  users_service_url, billing_service_url, users_service_token,
  billing_service_token, acting_user.
- Авторизація на рівні колекції: OAuth 2.0 (Authorization Code + PKCE,
  Google, scopes openid email), Client ID — змінна; токен кладеться
  в google_access_token. Альтернатива — вставити токен вручну.
- Папки й запити:
    - Public: GET /.well-known/oauth-protected-resource, GET /health/live,
      GET /health/ready.
    - Auth checks: MCP-запит без токена → 401 + WWW-Authenticate;
      з чужим Origin → 403.
    - MCP (JSON-RPC 2.0 через POST на mcp_path, заголовки
      Accept: application/json, text/event-stream, MCP-Protocol-Version,
      Mcp-Session-Id): initialize (test-скрипт зберігає Mcp-Session-Id
      з відповіді в mcp_session_id), notifications/initialized, tools/list,
      tools/call для кожної з шести тулз (з tenant_id і дефолтними
      параметрами), tools/call з невалідним limit, закриття сесії
      (DELETE, якщо бандл це підтримує).
    - Backends (для локального дебагу, напряму в internal API з service
      token і X-Acting-User): усі шість ендпоінтів Users і Billing.
- У кожному запиті — Postman tests: статус-код, Content-Type, наявність
  ключових полів, відсутність PII-ключів, для tools/list — наявність
  усіх шести назв тулз.
- Формат SSE і точні заголовки бери з коду бандла у vendor/, а не з пам'яті.
- Колекцію можна запускати з CLI: опиши в README команду
  `npx newman run postman/mcp-server.postman_collection.json -e postman/local.postman_environment.json`
  (для запитів, що не потребують інтерактивного OAuth).

### README
- Архітектура: мікросервіси, їхні зони відповідальності, як MCP-сервер
  з ними взаємодіє, список тулз, версія контракту (v1).
- Налаштування Google Cloud: OAuth consent screen типу Internal, OAuth
  client, scopes openid email, redirect URI клієнтів (вказати, що точні
  значення треба взяти з документації клієнтів).
- Звідки брати service token для кожного з двох сервісів.
- Підключення Claude Code і кастомного конектора claude.ai.
- Перевірка через MCP Inspector (npx @modelcontextprotocol/inspector).
- Перевірка через Postman: імпорт колекції й оточення, отримання
  Google-токена, порядок запитів (initialize → tools/list → tools/call).

## Наприкінці
Підсумок: структура проєкту, env-змінні, як запустити локально,
як імпортувати Postman-колекцію, що лишилось налаштувати вручну (Google Cloud, ingress, секрети для
обох сервісів).
