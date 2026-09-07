# Wallgold Price Alerts

Gold price alert service built on Laravel 13. A user registers a target gold price; when the global price reaches or crosses it, the user is emailed the new price once and the alert is removed.

The service is API-only: there is no frontend, the root route returns service metadata and `/up` is the health check.

## Quick start (Docker)

Requirements: Docker with Compose v2. Everything else runs in containers.

```bash
make up          # builds the image, writes .env with an application key, starts the stack
make logs        # follow all services
make test        # run the test suite inside the container
make down        # stop the stack (data volumes are kept)
```

Without `make`, the equivalent is:

```bash
cp .env.example .env
docker compose build app
docker compose run --rm --no-deps app php artisan key:generate --show   # paste into APP_KEY in .env
docker compose up -d
```

The API listens on http://localhost:8000 (`/up` is the health check) and Mailpit's inbox is at http://localhost:8025.

### Mirrors

Images and packages are pulled from ArvanCloud's mirrors by default (`docker.arvancloud.ir` for Docker Hub images, `mirror.arvancloud.ir/alpine` for Alpine packages) so the stack builds quickly from inside Iran. Outside Iran, or if the mirrors are unreachable, set these in `.env` before `make up`:

```dotenv
DOCKER_REGISTRY=docker.io
APK_MIRROR=https://dl-cdn.alpinelinux.org/alpine
```

Composer has no ArvanCloud mirror; `COMPOSER_MIRROR` accepts any Packagist-compatible repository URL if you need one.

## Running the tests on the host

The test suite uses an in-memory SQLite database and needs no services:

```bash
composer install
php artisan test
```

## Price feed

The global gold price comes from a `PriceProvider` selected by `GOLD_PRICE_PROVIDER`:

- `fake` (default): a seeded random walk starting at `GOLD_FAKE_START` that moves at most `GOLD_FAKE_MAX_STEP` per tick. It can be steered to exact values for demos.
- `goldapi`: XAU/USD from goldapi.io using `GOLD_API_URL` and `GOLD_API_TOKEN`.

Every provider returns a quote (price, observation time, source) or throws `PriceUnavailable`, so callers can back off without inspecting provider-specific errors. Prices are handled with four decimal places as exact integers.
