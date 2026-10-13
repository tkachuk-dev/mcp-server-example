# Local development of the monorepo: users-service (Symfony), billing-service (Laravel), mcp-server.
# Run `make` or `make help` to list the commands.

.DEFAULT_GOAL := help
.PHONY: help network up up-users up-billing up-mcp up-mcp-stubs down down-users down-billing down-mcp \
	    restart build ps logs logs-users logs-billing logs-mcp health \
	    migrate migrate-users migrate-billing seed seed-users seed-billing fresh-billing destroy \
	    sh-users sh-billing sh-mcp db-users db-billing redis-users redis-billing console artisan tinker mcp-console \
	    install test test-users test-billing test-mcp lint lint-users lint-billing lint-mcp fix \
	    contract-sync newman token

NETWORK    := internal-ai
DEV_TOKEN  := dev-mcp-token
DEV_ACTOR  := dev@example.com
TENANT     ?= 1

USERS_DC   := docker compose -f symfony-service/compose.yaml
BILLING_DC := docker compose -f laravel-service/compose.yaml
MCP_DC     := docker compose -f mcp-server/compose.yaml
MCP_STUBS_DC := docker compose -f mcp-server/compose.yaml -f mcp-server/compose.stubs.yaml

CURL_INTERNAL := curl -s -H 'Authorization: Bearer $(DEV_TOKEN)' -H 'X-Acting-User: $(DEV_ACTOR)'

help: ## Show this help
	@awk 'BEGIN {FS = ":.*## "} /^[a-zA-Z_-]+:.*## / {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2} /^## / {printf "\n\033[1m%s\033[0m\n", substr($$0, 4)}' $(MAKEFILE_LIST)

## Stack

network: ## Create the shared docker network (once)
	@docker network inspect $(NETWORK) >/dev/null 2>&1 || docker network create $(NETWORK)

up: network up-users up-billing up-mcp ## Start all three services (build, migrate)
	@echo "mcp-server http://localhost:8080/mcp | users http://localhost:8081 | billing http://localhost:8082"

up-users: network ## Start users-service and run migrations
	$(USERS_DC) up -d --build --wait
	$(USERS_DC) exec -T users-service bin/console doctrine:migrations:migrate -n --allow-no-migration

up-billing: network ## Start billing-service (migrations run on start)
	$(BILLING_DC) up -d --build --wait

up-mcp: network ## Start mcp-server next to the real backends
	$(MCP_DC) up -d --build --wait

up-mcp-stubs: network ## Start mcp-server with WireMock stubs instead of the backends
	$(MCP_STUBS_DC) up -d --build --wait

down: down-mcp down-billing down-users ## Stop all services (data volumes are kept)

down-users: ## Stop users-service
	$(USERS_DC) down

down-billing: ## Stop billing-service
	$(BILLING_DC) down

down-mcp: ## Stop mcp-server (and the stubs, if running)
	$(MCP_STUBS_DC) down --remove-orphans

restart: down up ## Restart all services

build: ## Rebuild all images without starting them
	$(USERS_DC) build
	$(BILLING_DC) build
	$(MCP_DC) build

ps: ## Show containers of all services
	@$(USERS_DC) ps
	@$(BILLING_DC) ps
	@$(MCP_DC) ps

logs: ## Follow logs of all three app containers
	@trap 'kill 0' INT; \
	$(USERS_DC) logs -f --tail=50 users-service & \
	$(BILLING_DC) logs -f --tail=50 billing-service & \
	$(MCP_DC) logs -f --tail=50 mcp-server & \
	wait

logs-users: ## Follow users-service logs
	$(USERS_DC) logs -f --tail=100 users-service

logs-billing: ## Follow billing-service logs
	$(BILLING_DC) logs -f --tail=100 billing-service

logs-mcp: ## Follow mcp-server logs
	$(MCP_DC) logs -f --tail=100 mcp-server

health: ## Check health endpoints and one internal endpoint per backend (TENANT=1)
	@for url in http://localhost:8080/health/ready http://localhost:8081/health/ready http://localhost:8082/health/ready; do \
	    printf '%-40s %s\n' "$$url" "$$(curl -s -o /dev/null -w '%{http_code}' $$url)"; \
	done
	@printf '%-40s %s\n' "users  /users-summary" "$$($(CURL_INTERNAL) -o /dev/null -w '%{http_code}' localhost:8081/internal/ai/v1/tenants/$(TENANT)/users-summary)"
	@printf '%-40s %s\n' "billing /rfm-summary" "$$($(CURL_INTERNAL) -o /dev/null -w '%{http_code}' localhost:8082/internal/ai/v1/tenants/$(TENANT)/rfm-summary)"
	@printf '%-40s %s\n' "mcp /mcp without token (expect 401)" "$$(curl -s -o /dev/null -w '%{http_code}' -X POST localhost:8080/mcp)"

## Data

migrate: migrate-users migrate-billing ## Run migrations in both backends

migrate-users: ## Run users-service migrations
	$(USERS_DC) exec -T users-service bin/console doctrine:migrations:migrate -n --allow-no-migration

migrate-billing: ## Run billing-service migrations
	$(BILLING_DC) exec -T billing-service php artisan migrate --force

seed: seed-users seed-billing ## Load demo data into both backends

# The fixtures purger uses DELETE, which keeps PostgreSQL sequences running, so the schema is recreated
# to keep the demo tenant ids at 1 and 2 (the same ids the billing-service demo data uses).
seed-users: ## Recreate the users-service schema and load fixtures (clears cached results)
	$(USERS_DC) exec -T users-service bin/console doctrine:schema:drop --force --full-database
	$(USERS_DC) exec -T users-service bin/console doctrine:migrations:migrate -n
	$(USERS_DC) exec -T users-service bin/console doctrine:fixtures:load -n
	$(USERS_DC) exec -T users-service bin/console cache:pool:clear cache.app

seed-billing: ## Load billing-service demo data (replaces the data, clears cached results)
	$(BILLING_DC) exec -T billing-service php artisan db:seed --force
	$(BILLING_DC) exec -T billing-service php artisan cache:clear

fresh-billing: ## Recreate the billing-service schema and seed demo data
	$(BILLING_DC) exec -T billing-service php artisan migrate:fresh --seed --force

destroy: ## Stop everything and DELETE database volumes
	@printf 'This deletes the users and billing databases. Continue? [y/N] '; read ans; [ "$$ans" = y ]
	$(MCP_STUBS_DC) down --remove-orphans
	$(BILLING_DC) down -v
	$(USERS_DC) down -v

## Shells and consoles

sh-users: ## Shell inside the users-service container
	$(USERS_DC) exec users-service bash

sh-billing: ## Shell inside the billing-service container
	$(BILLING_DC) exec billing-service bash

sh-mcp: ## Shell inside the mcp-server container
	$(MCP_DC) exec mcp-server sh

db-users: ## psql into the users-service database
	$(USERS_DC) exec database psql -U app -d users

db-billing: ## psql into the billing-service database
	$(BILLING_DC) exec billing-db psql -U billing -d billing

redis-users: ## redis-cli into the users-service Redis
	$(USERS_DC) exec redis redis-cli

redis-billing: ## redis-cli into the billing-service Redis (cache is DB 1)
	$(BILLING_DC) exec billing-redis redis-cli

console: ## Symfony console in users-service, e.g. make console c="debug:router"
	$(USERS_DC) exec users-service bin/console $(c)

artisan: ## Artisan in billing-service, e.g. make artisan c="route:list --path=internal"
	$(BILLING_DC) exec billing-service php artisan $(c)

tinker: ## Laravel tinker in billing-service
	$(BILLING_DC) exec billing-service php artisan tinker

mcp-console: ## Symfony console in mcp-server, e.g. make mcp-console c="debug:router"
	$(MCP_DC) exec mcp-server php bin/console $(c)

## Quality (runs on the host, needs PHP and Composer)

install: ## composer install in all three services
	cd symfony-service && composer install
	cd laravel-service && composer install
	cd mcp-server && composer install

test: test-users test-billing test-mcp ## Run all test suites

test-users: ## users-service tests
	cd symfony-service && vendor/bin/phpunit

test-billing: ## billing-service tests
	cd laravel-service && php artisan test

test-mcp: ## mcp-server tests
	cd mcp-server && composer test

lint: lint-users lint-billing lint-mcp ## Static analysis and code style checks for all services

lint-users: ## PHPStan and PHP-CS-Fixer (check only) for users-service
	cd symfony-service && vendor/bin/phpstan analyse --no-progress
	cd symfony-service && PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --dry-run --diff

lint-billing: ## Larastan and Pint (check only) for billing-service
	cd laravel-service && vendor/bin/phpstan analyse --memory-limit=1G --no-progress
	cd laravel-service && vendor/bin/pint --test

lint-mcp: ## PHPStan and PHP-CS-Fixer (check only) for mcp-server
	cd mcp-server && composer phpstan
	cd mcp-server && PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --dry-run --diff

fix: ## Apply code style fixes in all services
	cd symfony-service && PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix
	cd laravel-service && vendor/bin/pint
	cd mcp-server && composer cs

## Tools

contract-sync: ## Copy backend OpenAPI specs into mcp-server and run its tests
	cd mcp-server && composer contract:sync && composer test

newman: ## Run the Postman folders that need no OAuth against the running stack
	cd mcp-server && npx -y newman run postman/mcp-server.postman_collection.json \
	    -e postman/local.postman_environment.json --folder Public --folder "Auth checks" --folder Backends

token: ## Generate a service token and its sha256 hash
	@token=$$(openssl rand -hex 32); \
	echo "token (mcp-server *_SERVICE_TOKEN):        $$token"; \
	echo "hash  (backend MCP_SERVICE_TOKEN_HASHES): $$(printf '%s' "$$token" | shasum -a 256 | cut -d' ' -f1)"
