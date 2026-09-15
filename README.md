# Test task

Symfony application built as a step-by-step take-home assignment.

## Requirements

- Docker and Docker Compose

## Running

```
cp .env .env.local
docker compose up -d --build
```

The app installs its dependencies during the image build. After the containers are up, apply the database migrations:

```
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```

With `make` installed, the same is available as:

```
make build
make up
make migrate
```

Other Makefile targets: `make down`, `make restart`, `make logs`, `make sh` (shell into the app container), `make test` (run the test suite, creating and migrating the test database first).

## Services

| Service | URL                     | Notes                                |
|---------|-------------------------|---------------------------------------|
| app     | http://localhost:8000   | Symfony application                   |
| db      | localhost:5432           | PostgreSQL 15, db/user/password: `symfony` |
| pgadmin | http://localhost:5050   | login `admin@admin.com` / `admin`     |

## Ports

Ports are configurable through environment variables (see `.env`), with the values above as defaults:

- `APP_PORT` — application HTTP port
- `DB_PORT` — PostgreSQL port
- `PGADMIN_PORT` — pgAdmin port

## API documentation

Once the app is running, the OpenAPI/Swagger documentation is available at:

- http://localhost:8000/api/doc — Swagger UI
- http://localhost:8000/api/doc.json — raw OpenAPI spec

## Endpoints

### `GET /api/price`

Looks up the price in EUR of a tile.expert article by scraping its product page.

Query parameters: `factory`, `collection`, `article` (all required).

```
curl "http://localhost:8000/api/price?factory=marca-corona&collection=arteseta&article=k263-arteseta-camoscio-s000628660"
```

```json
{"price":59.99,"factory":"marca-corona","collection":"arteseta","article":"k263-arteseta-camoscio-s000628660"}
```

Returns `400` when a parameter is missing, `404` when tile.expert has no matching article.

### `GET /api/orders/stats`

Number of orders grouped by day, month or year, paginated.

Query parameters: `group` (`day`, `month` or `year`, required), `page` (default `1`), `perPage` (default `20`, max `100`).

```
curl "http://localhost:8000/api/orders/stats?group=month&page=1&perPage=20"
```

```json
{
  "page": 1,
  "perPage": 20,
  "totalItems": 3,
  "totalPages": 1,
  "group": "month",
  "items": [
    {"period": "2026-02", "count": 2},
    {"period": "2026-01", "count": 2},
    {"period": "2025-12", "count": 1}
  ]
}
```

Returns `400` on an invalid `group`, `page` or `perPage`.

## Tests

```
make test
```

or directly:

```
docker compose exec app php bin/phpunit
```
