COMPOSE ?= docker compose
APP = $(COMPOSE) run --rm --no-deps app
FRONTEND = $(COMPOSE) run --rm frontend

.PHONY: setup up down build test test-php test-js infection mutation coverage frontend-build logs

setup:
	@test -f .env || cp .env.example .env
	@test -f backend/.env || cp backend/.env.example backend/.env
	@mkdir -p data

up: setup
	$(COMPOSE) up --build

down:
	$(COMPOSE) down

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
