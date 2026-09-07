COMPOSE ?= docker compose

.PHONY: up down build logs shell test

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

shell:
	$(COMPOSE) exec app sh

## Run the test suite inside the container (SQLite in memory, Redis from the stack when running).
test: .env
	$(COMPOSE) run --rm --no-deps app php artisan test

.env:
	cp .env.example .env
