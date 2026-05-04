# Product API (Phalcon + MySQL + Volt + Vue 3)

Микросервис каталога товаров на **PHP Phalcon 5**, **MySQL 8**, веб-панель на **Volt** и **Vue 3** (сборка **Vite**), REST API с **Bearer Token**.

## Требования

- Docker и Docker Compose **или** PHP 8.2+ с расширениями `phalcon`, `psr`, `pdo_mysql`
- MySQL 8+ (нужен `WITH RECURSIVE` для выборки поддерева категории по `slug`)
- Для пересборки панели: **Node.js 20+** и npm; зависимости и `vite.config.js` — в **корне** репозитория, исходники Vue — в `frontend/`

## Быстрый старт (Docker)

```bash
cp .env.example .env
docker compose up --build -d
```

- HTTP: **http://localhost:8888/** — UI (Volt + собранный Vue-бандл из `public/build/`)
- API: **http://localhost:8888/api/v1/**  
- **Swagger UI:** **http://localhost:8888/swagger.html**

```bash
composer install
cp .env.example .env
php cli/migrate.php
```

Повторный запуск `php cli/migrate.php` без флага завершается успешно и **ничего не меняет**, если таблицы уже есть. Чтобы заново накатить `schema.sql` с **DROP** таблиц (данные уничтожаются): `php cli/migrate.php --force`.

Схема и индексы задаются в `database/schema.sql`.

### Фронтенд панели (Vite)

Исходники: `frontend/` (Vue SFC), конфиг сборки: **`vite.config.js` в корне**. Артефакты: `public/build/panel.js` и `public/build/panel.css`.

Из **корня** репозитория:

```bash
npm install
npm run build
```

Разработка с HMR:

```bash
npm run dev
```

Откройте URL из вывода Vite (по умолчанию порт **5173**). Запросы к `/api` проксируются на **http://127.0.0.1:8888** (поднимите Docker или локальный PHP рядом).

### Индексы MySQL

| Таблица | Индекс | Колонки | Назначение |
|---------|--------|---------|------------|
| `categories` | PRIMARY KEY | `id` | Идентификатор категории |
| `categories` | `uq_categories_slug` (UNIQUE) | `slug` | Уникальный slug, быстрый поиск по slug |
| `categories` | `idx_categories_parent` | `parent_id` | Связь с родителем и обход дерева по родителю |
| `products` | PRIMARY KEY | `id` | Идентификатор товара |
| `products` | `idx_products_category_stock_id` | `category_id`, `in_stock`, `id` | Фильтрация по категории и наличию, стабильная сортировка/пагинация по `id` |
| `products` | `idx_products_in_stock_category` | `in_stock`, `category_id` | Запросы с упором на фильтр по наличию и категории |
| `api_tokens` | PRIMARY KEY | `id` | Идентификатор записи токена |
| `api_tokens` | `uq_api_tokens_hash` (UNIQUE) | `token_hash` | Проверка Bearer по хешу без скана таблицы |

Лимит выдачи токенов (`POST /api/v1/tokens/generate`): переменные `TOKEN_GENERATE_LIMIT` и `TOKEN_GENERATE_WINDOW` в `.env` (по умолчанию 30 запросов с одного IP за 3600 сек; `0` — без лимита).

## API (кратко)

| Метод | Путь | Описание |
|--------|------|----------|
| POST | `/api/v1/tokens/generate` | Выдать новый токен (тело: опционально `name`) |
| GET | `/api/v1/tokens` | Список токенов (без plain-секрета) |
| DELETE | `/api/v1/tokens/{id}` | Отозвать токен (410 при успехе) |
| GET | `/api/v1/products` | Список: `category` (slug), `in_stock`, `page`, `per_page`. В ответе — `data`, `pagination`, `aggregates` (см. ниже) |
| GET/POST/PUT/PATCH/DELETE | `/api/v1/products[/{id}]` | CRUD товара; поля: `name`, `content`, `price`, `quantity`, `category_slug`, `in_stock` |
| GET | `/api/v1/categories` | Плоский список |
| GET | `/api/v1/categories/tree` | Дерево |
| GET/POST/PUT/PATCH/DELETE | `/api/v1/categories[/{id}]` | CRUD категории; `parent_id` для подкатегории |
# products-api
