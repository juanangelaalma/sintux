# ──────────────────────────────────────────────
# Sintux – Laravel Modular Monolith Makefile
# ──────────────────────────────────────────────

# Use Sail by default (set SAIL=0 to use bare php artisan)
SAIL ?= 1
ifeq ($(SAIL),1)
  ARTISAN = ./vendor/bin/sail artisan
  COMPOSE = docker compose
  EXEC    = ./vendor/bin/sail
else
  ARTISAN = php artisan
  COMPOSE =
  EXEC    =
endif

MODULES_DIR = app/Modules

# ── Help ──────────────────────────────────────
.PHONY: help
help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-22s\033[0m %s\n", $$1, $$2}'

# ── Setup ─────────────────────────────────────
.PHONY: setup
setup: ## First-time setup (env, key, migrate, npm)
	composer install
	@test -f .env || cp .env.example .env
	$(ARTISAN) key:generate
	$(ARTISAN) migrate --force
	npm install
	npm run build

# ── Dev Server ────────────────────────────────
.PHONY: dev
dev: ## Start dev server (Laravel + Vite)
	$(ARTISAN) dev

.PHONY: up
up: ## Start Docker containers
	$(COMPOSE) up -d

.PHONY: down
down: ## Stop Docker containers
	$(COMPOSE) down

.PHONY: restart
restart: down up ## Restart Docker containers

# ── Database ──────────────────────────────────
.PHONY: migrate
migrate: ## Run migrations
	$(ARTISAN) migrate

.PHONY: migrate-fresh
migrate-fresh: ## Drop all tables and re-migrate
	$(ARTISAN) migrate:fresh

.PHONY: migrate-fresh-seed
migrate-fresh-seed: ## Drop, re-migrate, and seed
	$(ARTISAN) migrate:fresh --seed

.PHONY: seed
seed: ## Run seeders
	$(ARTISAN) db:seed

.PHONY: rollback
rollback: ## Rollback last migration batch
	$(ARTISAN) migrate:rollback

# ── Modules (nwidart/laravel-modules) ─────────
.PHONY: module-make
module-make: ## Create a new module  (make module-make M=ModuleName)
	@test -n "$(M)" || (echo "Usage: make module-make M=ModuleName" && exit 1)
	$(ARTISAN) module:make $(M)

.PHONY: module-migrate
module-migrate: ## Run module migrations
	$(ARTISAN) module:migrate

.PHONY: module-seed
module-seed: ## Seed a module  (make module-seed M=ModuleName)
	@test -n "$(M)" || (echo "Usage: make module-seed M=ModuleName" && exit 1)
	$(ARTISAN) module:seed $(M)

.PHONY: module-list
module-list: ## List all modules
	$(ARTISAN) module:list

.PHONY: module-enable
module-enable: ## Enable a module  (make module-enable M=ModuleName)
	@test -n "$(M)" || (echo "Usage: make module-enable M=ModuleName" && exit 1)
	$(ARTISAN) module:enable $(M)

.PHONY: module-disable
module-disable: ## Disable a module  (make module-disable M=ModuleName)
	@test -n "$(M)" || (echo "Usage: make module-disable M=ModuleName" && exit 1)
	$(ARTISAN) module:disable $(M)

.PHONY: module-publish
module-publish: ## Publish module config/assets
	$(ARTISAN) module:publish-config

.PHONY: module-make-controller
module-make-controller: ## Create controller in module  (make module-make-controller M=Sales C=InvoiceController)
	@test -n "$(M)" && test -n "$(C)" || (echo "Usage: make module-make-controller M=Module C=ControllerName" && exit 1)
	$(ARTISAN) module:make-controller $(C) $(M)

.PHONY: module-make-model
module-make-model: ## Create model in module  (make module-make-model M=Sales N=Invoice)
	@test -n "$(M)" && test -n "$(N)" || (echo "Usage: make module-make-model M=Module N=ModelName" && exit 1)
	$(ARTISAN) module:make-model $(N) $(M)

.PHONY: module-make-migration
module-make-migration: ## Create migration in module  (make module-make-migration M=Sales N=create_invoices_table)
	@test -n "$(M)" && test -n "$(N)" || (echo "Usage: make module-make-migration M=Module N=MigrationName" && exit 1)
	$(ARTISAN) module:make-migration $(N) $(M)

.PHONY: module-make-seeder
module-make-seeder: ## Create seeder in module  (make module-make-seeder M=Sales N=InvoiceSeeder)
	@test -n "$(M)" && test -n "$(N)" || (echo "Usage: make module-make-seeder M=Module N=SeederName" && exit 1)
	$(ARTISAN) module:make-seeder $(N) $(M)

.PHONY: module-make-request
module-make-request: ## Create form request in module  (make module-make-request M=Sales N=StoreInvoiceRequest)
	@test -n "$(M)" && test -n "$(N)" || (echo "Usage: make module-make-request M=Module N=RequestName" && exit 1)
	$(ARTISAN) module:make-request $(N) $(M)

# ── NPM / Frontend ───────────────────────────
.PHONY: npm-install
npm-install: ## Install npm dependencies
	npm install

.PHONY: npm-dev
npm-dev: ## Run Vite dev server (HMR)
	npm run dev

.PHONY: npm-build
npm-build: ## Build frontend assets
	npm run build

.PHONY: npm-lint
npm-lint: ## Lint JS/TS with ESLint (auto-fix)
	npm run lint

.PHONY: npm-lint-check
npm-lint-check: ## Check JS/TS lint (no fix)
	npm run lint:check

.PHONY: npm-format
npm-format: ## Format with Prettier
	npm run format

.PHONY: npm-format-check
npm-format-check: ## Check Prettier formatting
	npm run format:check

.PHONY: npm-types
npm-types: ## TypeScript type check
	npm run types:check

# ── PHP Quality ───────────────────────────────
.PHONY: lint
lint: ## Run Laravel Pint (auto-fix)
	vendor/bin/pint --parallel

.PHONY: lint-check
lint-check: ## Check PHP style (no fix)
	vendor/bin/pint --parallel --test

.PHONY: types
types: ## Run PHPStan
	vendor/bin/phpstan analyse

.PHONY: test
test: ## Run full test suite
	$(ARTISAN) config:clear --ansi
	vendor/bin/pint --parallel --test
	vendor/bin/phpstan analyse
	$(ARTISAN) test

.PHONY: test-quick
test-quick: ## Run PHPUnit only (skip lint/types)
	$(ARTISAN) test

# ── Cache & Cleanup ──────────────────────────
.PHONY: clear
clear: ## Clear all caches
	$(ARTISAN) cache:clear
	$(ARTISAN) config:clear
	$(ARTISAN) route:clear
	$(ARTISAN) view:clear
	$(ARTISAN) event:clear

.PHONY: optimize
optimize: ## Cache config, routes, views
	$(ARTISAN) optimize

.PHONY: tinker
tinker: ## Open Laravel Tinker
	$(ARTISAN) tinker

# ── CI ────────────────────────────────────────
.PHONY: ci
ci: lint-check npm-lint-check npm-format-check npm-types types test-quick ## Run all CI checks
