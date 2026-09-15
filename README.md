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

Then create the Manticore Search table and index any existing orders:

```
docker compose exec app php bin/console app:orders:reindex
```

With `make` installed, the same is available as:

```
make build
make up
make migrate
make reindex
```

Other Makefile targets: `make down`, `make restart`, `make logs`, `make sh` (shell into the app container), `make test` (run the test suite, creating and migrating the test database first).

## Services

| Service   | URL                     | Notes                                |
|-----------|-------------------------|---------------------------------------|
| app       | http://localhost:8000   | Symfony application                   |
| db        | localhost:5432          | PostgreSQL 15, db/user/password: `symfony` |
| pgadmin   | http://localhost:5050   | login `admin@admin.com` / `admin`     |
| manticore | http://localhost:9308   | Manticore Search HTTP API             |

## Ports

Ports are configurable through environment variables (see `.env`), with the values above as defaults:

- `APP_PORT` — application HTTP port
- `DB_PORT` — PostgreSQL port
- `PGADMIN_PORT` — pgAdmin port
- `MANTICORE_PORT` — Manticore Search HTTP API port

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

### `GET /api/orders/{id}`

Get a single order.

```
curl "http://localhost:8000/api/orders/1"
```

```json
{"id":1,"customerName":"John Doe","amount":42.5,"createdAt":"2026-03-01T10:00:00+00:00"}
```

Returns `404` when the order does not exist.

### `POST /api/soap/orders` (SOAP)

Creates an order from a SOAP request. The WSDL is served on `GET /api/soap/orders`.

```php
$client = new SoapClient('http://localhost:8000/api/soap/orders', ['cache_wsdl' => WSDL_CACHE_NONE]);
$id = $client->createOrder('John Doe', '42.50', '2026-03-01T10:00:00+00:00');
```

`createOrder(customerName: string, amount: string, createdAt: string = '')` returns the created order's `id` (int). `createdAt` is optional and defaults to the current time. Invalid input (empty `customerName`, non-numeric `amount`, invalid `createdAt`) raises a SOAP Fault.

Orders created through this endpoint are automatically indexed in Manticore Search (best-effort — order creation itself does not fail if Manticore is unreachable).

### `GET /api/orders/search`

Full-text search of orders by customer name, powered by Manticore Search.

Query parameters: `q` (required), `page` (default `1`), `perPage` (default `20`, max `100`).

```
curl "http://localhost:8000/api/orders/search?q=John&page=1&perPage=20"
```

```json
{
  "page": 1,
  "perPage": 20,
  "totalItems": 1,
  "totalPages": 1,
  "query": "John",
  "items": [
    {"id": 1, "customerName": "John Doe", "amount": 42.5, "createdAt": "2026-03-01T10:00:00+00:00"}
  ]
}
```

Returns `400` when `q`, `page` or `perPage` is invalid, `503` when Manticore is unreachable. Run `make reindex` (or `php bin/console app:orders:reindex`) any time to (re)create the Manticore table and backfill it from the database — useful the first time, or if orders were created while Manticore was down.

## Tests

```
make test
```

or directly:

```
docker compose exec app php bin/phpunit
```
