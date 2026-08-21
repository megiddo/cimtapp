COMPOSE ?= docker compose
COMPOSE_PROD ?= $(COMPOSE) -f docker-compose.prod.yml
APP = $(COMPOSE) run --rm --no-deps app
FRONTEND = $(COMPOSE) run --rm frontend

.PHONY: setup up up-prod down down-prod build test test-php test-js infection mutation coverage frontend-build logs

setup:
	@test -f .env || cp .env.example .env
	@test -f backend/.env || cp backend/.env.example backend/.env
	@mkdir -p data

up: setup
	$(COMPOSE) up --build

up-prod: setup
	$(COMPOSE_PROD) up --build

down:
	$(COMPOSE) down

down-prod:
	$(COMPOSE_PROD) down

build:
	$(COMPOSE) build

test: test-php test-js

test-php:
	$(APP) composer test

test-js:
	$(FRONTEND) npm ci
	$(FRONTEND) npm test

infection:
	$(APP) composer infection

mutation: infection
	$(FRONTEND) npm ci
	$(FRONTEND) npm run mutation

coverage: test

frontend-build:
	$(FRONTEND) npm ci
	$(FRONTEND) npm run build

logs:
	$(COMPOSE) logs -f app
