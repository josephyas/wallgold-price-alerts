COMPOSE ?= docker compose

APP_PORT ?= $(shell sed -n 's/^APP_PORT=//p' .env 2>/dev/null)
ifeq ($(strip $(APP_PORT)),)
APP_PORT := 8000
endif
PRICES ?= 2690 2701.25

.PHONY: up down build logs watch shell test token push

## Build the image, create .env with an application key if needed, start everything.
up: .env
	$(COMPOSE) build app
	@grep -q '^APP_KEY=base64:' .env || { \
		key=$$($(COMPOSE) run --rm --no-deps -T app php artisan key:generate --show); \
		sed -i.bak "s|^APP_KEY=.*|APP_KEY=$$key|" .env && rm -f .env.bak; \
		echo "APP_KEY written to .env"; }
	$(COMPOSE) up -d

## Stop and remove containers (volumes are kept).
down:
	$(COMPOSE) down --remove-orphans

build:
	$(COMPOSE) build app

logs:
	$(COMPOSE) logs -f --tail=100

## Follow the watcher: one line per price tick.
watch:
	$(COMPOSE) logs -f --tail=20 watcher

shell:
	$(COMPOSE) exec app sh

## Print a bearer token for the seeded demo user.
token:
	@curl -sS --fail -X POST localhost:$(APP_PORT)/api/auth/token -H 'Accept: application/json' -H 'Content-Type: application/json' \
		-d '{"email":"demo@example.com","password":"password","device_name":"make"}' | sed -E 's/.*"token":"([^"]+)".*/\1/'

## Steer the fake price feed, e.g. make push PRICES="2690 2701.25"
push:
	$(COMPOSE) exec -T app php artisan price:fake-push $(PRICES)

## Run the test suite inside the container (SQLite in memory, Redis from the stack when running).
test: .env
	$(COMPOSE) run --rm --no-deps app sh -c 'unset DB_CONNECTION QUEUE_CONNECTION CACHE_STORE MAIL_MAILER; exec php artisan test'

.env:
	cp .env.example .env
