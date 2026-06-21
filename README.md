# Developer Landing Backend

Backend-сервис для лендинга-презентации разработчика: приём заявок с формы обратной
связи, AI-анализ комментариев (Anthropic Claude), email-уведомления, статистика
обращений.

## Стек технологий

- **PHP 8.2+, Laravel 11** — даёт готовую валидацию (Form Request), Mail,
  логирование, rate limiting и контейнер зависимостей без написания
  собственной обвязки.
- **Хранение данных — только файловое** (JSON / JSON Lines в `storage/app/data/`).
  База данных не используется — `config/database.php` и блок `connections`
  намеренно пустые.
- **Почта** — `Mail`-фасад Laravel, локально через [Mailpit](https://github.com/axllent/mailpit)
  (SMTP-заглушка с веб-интерфейсом), в проде — любой SMTP-провайдер.
- **AI** — [Anthropic API](https://docs.anthropic.com/) (модель Claude), вызывается
  напрямую через `Http`-фасад (Guzzle), без SDK.
- **Документация API** — статический `openapi.yaml` (OpenAPI 3.0) + Swagger UI
  (CDN-сборка, без публикации ассетов).
- **Тесты** — PHPUnit (Feature-тесты на все эндпоинты, включая AI fallback и rate
  limiting), Laravel Pint (PSR-12).

## Архитектура

Слоистая структура: `Controller → Service → Repository`.

```
app/
├── Http/
│   ├── Controllers/Api/        # тонкие контроллеры: ContactController, HealthController, MetricsController
│   ├── Requests/                # ContactRequest — валидация + единый формат 422-ответа
│   └── Middleware/LogRequests.php
├── Services/
│   ├── ContactService.php       # оркестрация: AI → письма → сохранение
│   └── AiService.php            # обёртка над Anthropic API с graceful fallback
├── Mail/
│   ├── OwnerContactNotification.php
│   └── UserContactConfirmation.php
├── Repositories/
│   └── ContactRepository.php    # файловое хранилище заявок и статистики
└── Exceptions/
    └── ApiExceptionRenderer.php # глобальная JSON-обработка ошибок (bootstrap/app.php)
```

Полный цикл обработки заявки:

```
запрос → ContactRequest (валидация)
       → AiService::analyzeComment()        (тональность, категория, черновик ответа; fallback при сбое)
       → ContactService::sendNotifications() (письмо владельцу + копия пользователю; не блокирует при сбое SMTP)
       → ContactRepository::save()           (запись в contacts.jsonl + обновление stats.json)
       → JSON-ответ 201
```

## Эндпоинты

| Метод | Путь            | Описание                                   |
|-------|-----------------|---------------------------------------------|
| POST  | `/api/contact`  | Приём заявки с формы обратной связи         |
| GET   | `/api/health`   | Статус сервиса                              |
| GET   | `/api/metrics`  | Статистика обращений (из файла)             |
| GET   | `/api/docs`     | Swagger UI                                  |
| GET   | `/api/openapi.yaml` | OpenAPI-спецификация                   |

Полная спецификация — в [`openapi.yaml`](openapi.yaml), либо открыть `/api/docs`
после запуска сервера.

### POST /api/contact

Валидация: `name` (строка, 2–100 симв.), `phone` (формат телефона), `email`
(валидный email), `comment` (строка, 5–2000 симв.).

```bash
curl -X POST http://localhost:8000/api/contact \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Иван Иванов",
    "phone": "+7 999 123-45-67",
    "email": "ivan@example.com",
    "comment": "Хочу обсудить разработку лендинга для моего проекта, бюджет до 100к."
  }'
```

Успешный ответ — `201`:

```json
{
  "success": true,
  "message": "Заявка принята. Мы свяжемся с вами в ближайшее время.",
  "data": {
    "mail_sent": true,
    "ai": { "used": true, "sentiment": "positive", "category": "cooperation" }
  }
}
```

Ошибка валидации — `422`, превышение лимита запросов — `429`, внутренняя ошибка — `500`.
Подробные примеры — в [`postman_collection.json`](postman_collection.json).

### GET /api/health

```bash
curl http://localhost:8000/api/health
```

```json
{
  "success": true,
  "status": "ok",
  "app": "Developer Landing Backend",
  "env": "local",
  "timestamp": "2026-06-21T10:00:00+00:00",
  "checks": { "storage_writable": true, "ai_configured": true }
}
```

### GET /api/metrics

```bash
curl http://localhost:8000/api/metrics
```

```json
{
  "success": true,
  "data": {
    "total_requests": 12,
    "mail_sent": 11,
    "mail_failed": 1,
    "ai_processed": 10,
    "ai_fallback": 2,
    "by_sentiment": { "positive": 6, "neutral": 3, "negative": 1 },
    "by_date": { "2026-06-21": 12 },
    "last_request_at": "2026-06-21T10:00:00+00:00"
  }
}
```

## AI-интеграция

`App\Services\AiService` отправляет комментарий пользователя в Anthropic Messages
API (`POST https://api.anthropic.com/v1/messages`) с единственным промптом:

```
Ты — ассистент, который помогает обрабатывать заявки с формы обратной связи на
лендинге разработчика. Проанализируй комментарий пользователя и верни ТОЛЬКО
валидный JSON без пояснений и markdown, строго такой структуры:

{"sentiment": "positive|neutral|negative", "category": "question|cooperation|order|complaint|other", "suggested_reply": "короткий вежливый черновик ответа на русском языке, 1-3 предложения"}

Комментарий пользователя:
"{comment}"
```

Из ответа модели достаётся тональность, категория обращения и черновик ответа,
который подставляется в письмо владельцу (как подсказка для ответа клиенту) и,
при наличии, в письмо-подтверждение пользователю.

**Graceful fallback.** Если `ANTHROPIC_API_KEY` не задан, Anthropic API недоступен,
вернул ошибку или прислал невалидный JSON — `AiService` логирует причину
(`Log::warning`) и возвращает нейтральный результат (`used: false`, остальные поля
`null`). `ContactService` не прерывает обработку заявки: письма всё равно
отправляются, заявка сохраняется, пользователь получает `201` без AI-данных в
ответе. То же самое верно и для отправки писем — сбой SMTP логируется
(`Log::error`), но не блокирует сохранение заявки.

## Хранение данных

Файловое хранилище в `storage/app/data/` (директория создаётся автоматически):

- **`contacts.jsonl`** — журнал заявок, JSON Lines (append-only), одна заявка —
  одна строка. Запись выполняется с файловой блокировкой (`flock`) для
  безопасности при параллельных запросах.
- **`stats.json`** — агрегированная статистика (общее число заявок, успешные/
  неудачные письма, использование AI, разбивка по тональности и датам).
  Обновляется атомарно при каждой заявке (`flock` + перезапись файла).

Логи запросов — `storage/logs/requests-*.log` (отдельный канал `requests` в
`config/logging.php`, ежедневная ротация). Логи приложения/ошибок —
`storage/logs/laravel-*.log` (стандартный канал Laravel).

Rate limiting на `/api/contact` реализован через встроенный `RateLimiter` Laravel
(`app/Providers/AppServiceProvider.php`), который хранит счётчики в **файловом
кеше** (`CACHE_STORE=file` в `.env`) — то есть тоже без БД и Redis. Лимит
настраивается через `CONTACT_RATE_LIMIT_MAX_ATTEMPTS` /
`CONTACT_RATE_LIMIT_DECAY_MINUTES` (по умолчанию 5 запросов в минуту с одного IP).

## Запуск проекта

### Требования

- PHP 8.2+ с расширениями `mbstring`, `pdo`, `openssl`, `curl` (входят в
  стандартную сборку)
- Composer 2.x

### Установка

```bash
composer install
cp .env.example .env
php artisan key:generate
```

### Настройка `.env`

Ключевые переменные (полный список — в `.env.example`):

```env
CONTACT_OWNER_EMAIL=owner@example.com   # куда падают заявки

# Почта (локально — Mailpit, см. ниже)
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025

# AI (Anthropic Claude)
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-3-5-haiku-20241022

# Rate limiting
CONTACT_RATE_LIMIT_MAX_ATTEMPTS=5
CONTACT_RATE_LIMIT_DECAY_MINUTES=1

CORS_ALLOWED_ORIGINS=*
```

Если `ANTHROPIC_API_KEY` не задан — сервис продолжит работать, просто без AI-анализа
(см. раздел про graceful fallback).

### Локальная почта (Mailpit)

```bash
docker run -d --name mailpit -p 1025:1025 -p 8025:8025 axllent/mailpit
```

Письма будут видны в веб-интерфейсе на http://localhost:8025. Без Mailpit заявки
всё равно будут приниматься и сохраняться — отправка писем просто завершится
ошибкой, которая залогируется (см. graceful fallback).

### Запуск сервера

```bash
php artisan serve
```

API будет доступен на http://localhost:8000, документация — на
http://localhost:8000/api/docs.

### Тесты

```bash
php artisan test
```

Покрыты: успешная отправка заявки (с моками AI и почты), валидация, AI fallback
при недоступности Anthropic API, rate limiting, health/metrics, обработка 404.

## Деплой

Проект не требует БД, поэтому деплоится как обычное Laravel-приложение на любой
PHP-хостинг (Railway, Render, любой VPS с PHP-FPM + Nginx). Переменные окружения —
как в `.env.example`. Для быстрой проверки локального запуска извне можно
прокинуть порт через [ngrok](https://ngrok.com/): `ngrok http 8000`.

## Что сделано с помощью AI

С помощью Claude Code сгенерированы: каркас Laravel-проекта, контроллеры,
сервисы, файловое хранилище, Form Request, глобальная обработка ошибок,
Mailable-классы, конфигурация rate limiting/CORS/логирования, OpenAPI-
спецификация и тесты.

Проверено вручную через `curl` и `php artisan test`, по ходу проверки
исправлены найденные баги: конфликт `readonly`-свойства в `AiService` и
порядок обработки `HttpResponseException` в `ApiExceptionRenderer` (влиял на
корректность `429`-ответа от rate limiter).
