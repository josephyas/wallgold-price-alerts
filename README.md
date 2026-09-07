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

## Alert index

Active alerts are mirrored into two Redis sorted sets, `alerts:above` and `alerts:below`, scored by target price in minor units with the alert id as member. A price tick is a single Lua script that takes every "above" alert at or under the price and every "below" alert at or over it, removes them from their sets and parks them in `alerts:inflight` until the delivery acknowledges them. Because the script is atomic, several tickers can run at once without popping the same alert twice, and the lookup costs O(log N + hits) however many alerts exist.

The index is a projection, not the record of truth: `alerts:ready` says whether it has been built from the database since Redis last started, and it can be rebuilt at any time without touching in-flight entries. The same contract has an in-memory implementation that the test suite uses, so the suite runs without Redis; tests marked `redis` exercise the real scripts when a Redis is reachable and are mandatory in CI.

## Delivery

Each popped alert becomes one `DeliverPriceAlert` job on the `alerts` queue. The job claims the row with a single conditional update (`active` to `sending`), which is atomic on every supported database, so two jobs for the same alert cannot both send. It then emails the user with the new price, deletes the row, and acknowledges the in-flight entry in the index.

A message the mail server refuses puts the alert back to `active` and lets the queue retry with backoff; after the last attempt the row is marked `failed` so the user can see it. A claim that never completes (the worker died mid-send) becomes claimable again after `GOLD_DELIVERY_STALE_AFTER_SECONDS`, which favours a rare duplicate over a silent miss.

## Watcher

`php artisan price:watch` is the long-running process that polls the feed every `GOLD_POLL_INTERVAL_MS` (default 1000, minimum 100) and matches each quote against the index. Every tick is one atomic pop per batch, followed by one pipelined push of the delivery jobs, so the watcher never waits on an email. A failing feed or index makes it back off exponentially (up to 10 s) rather than exit; `SIGTERM` stops it cleanly. `--once` runs a single tick (useful for cron, health checks and tests) and `-v` prints one line per tick.

The interval is bounded by the provider: the fake feed is comfortable at a few hundred milliseconds, while real HTTP APIs usually allow far fewer calls. A streaming provider would be the upgrade path for sub-second latency.

`php artisan price:fake-push 2690 2701.25` steers the fake feed to exact values on its next ticks, which is how the demo walks a price across an alert.

## API

All endpoints live under `/api`, speak JSON, and are rate limited (`GOLD_API_RATE_PER_MINUTE` per user, 10 per minute for the authentication endpoints). Authentication uses Sanctum bearer tokens.

| Method and path | Auth | Body | Response |
|---|---|---|---|
| `POST /api/auth/register` | none | `name`, `email`, `password`, optional `device_name` | 201 `{token, token_type, user}` |
| `POST /api/auth/token` | none | `email`, `password`, optional `device_name` | 200 `{token, token_type}` |
| `GET /api/auth/me` | token | | 200 `{data: {id, name, email}}` |
| `DELETE /api/auth/logout` | token | | 204, revokes the current token |
| `GET /api/price` | token | | 200 `{data: {price, unit, source, observed_at, received_at, stale}}`, or 503 before the first tick |
| `GET /api/alerts` | token | query `status`, `per_page` | 200 paginated list of the caller's alerts, newest first |
| `POST /api/alerts` | token | `target_price` (up to 4 decimals), optional `direction` (`above`/`below`) | 201 the alert |
| `GET /api/alerts/{id}` | token, owner | | 200 the alert |
| `DELETE /api/alerts/{id}` | token, owner | | 204, or 409 while the alert is being delivered |

Validation failures return 422 with an `errors` object; missing or revoked tokens return 401; another user's alert returns 403; exceeding a limit returns 429.

Direction rules for `POST /api/alerts`:

- Without `direction`, the target is compared with the current price: above it watches for a rise, under it for a fall. A target equal to the current price is refused as ambiguous.
- With `direction`, the alert is accepted as long as the current price does not already satisfy it. This is also the only way to create an alert while no fresh price is known (`GET /api/price` reports `stale: true` or 503).
- A user may hold `GOLD_MAX_ALERTS_PER_USER` alerts and one alert per level and side; a previous alert at the same level that ended in `failed` is replaced automatically.

### Walkthrough

```bash
TOKEN=$(curl -s -X POST localhost:8000/api/auth/token -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"demo@example.com","password":"password","device_name":"cli"}' | sed -E 's/.*"token":"([^"]+)".*/\1/')
AUTH=(-H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' -H 'Content-Type: application/json')

curl -s localhost:8000/api/price "${AUTH[@]}"
curl -s -X POST localhost:8000/api/alerts "${AUTH[@]}" -d '{"target_price":"2700"}'
docker compose exec app php artisan price:fake-push 2690 2701.25   # walk the price across the alert
open http://localhost:8025                                          # the email, with the new price
curl -s -o /dev/null -w '%{http_code}\n' localhost:8000/api/alerts/1 "${AUTH[@]}"   # 404: delivered alerts are deleted
```

## Reconciliation

`php artisan alerts:reconcile` runs every minute from the scheduler and is the safety net for everything that can drift:

- **Index not ready or behind the database** (Redis restarted, or an index write failed after a commit): the index is rebuilt from the active rows. `--rebuild` forces this.
- **Popped but never delivered** (a watcher died between popping and dispatching, or a job was lost): in-flight entries older than `GOLD_INDEX_INFLIGHT_TTL_SECONDS` are dispatched again, but only while the alerts queue is idle, so a backlog is never doubled.
- **Stuck in `sending`** (a worker died mid-send): rows older than `GOLD_DELIVERY_STALE_AFTER_SECONDS` are dispatched again; the delivery job's claim re-admits them.

Every action is idempotent because the delivery job's conditional claim decides who sends. A duplicate dispatch loses the claim and does nothing.
