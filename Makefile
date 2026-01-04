.PHONY: help up down restart build clean logs shell test install db-migrate db-reset lint frontend-test backend-test behat status ps

##
## 🏗️  Family Plan - Project Makefile
##

help: ## Display this help message
	@echo "Family Plan - Available Commands:"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'
	@echo ""

##
## 🐳 Docker Commands
##

up: ## Start all services (development mode)
	docker compose up -d

up-build: ## Start all services with rebuild
	docker compose up -d --build

down: ## Stop all services
	docker compose down

restart: ## Restart all services
	docker compose restart

stop: ## Stop all services (without removing containers)
	docker compose stop

start: ## Start previously created containers
	docker compose start

clean: ## Stop and remove all containers, networks, and volumes
	docker compose down -v --remove-orphans

clean-all: ## Clean everything including images
	docker compose down -v --remove-orphans --rmi all

ps: ## Show status of all containers
	docker compose ps

status: ps ## Alias for ps

logs: ## Show logs from all services
	docker compose logs -f

logs-php: ## Show PHP service logs
	docker compose logs -f php

logs-nginx: ## Show Nginx service logs
	docker compose logs -f nginx

logs-db: ## Show database logs
	docker compose logs -f database

logs-frontend: ## Show frontend service logs
	docker compose logs -f frontend

##
## 🚀 Production/Hostinger Commands
##

prod-up: ## Start production stack (Hostinger)
	docker compose -f docker-compose.hostinger.yml up -d

prod-down: ## Stop production stack
	docker compose -f docker-compose.hostinger.yml down

prod-restart: ## Restart production stack
	docker compose -f docker-compose.hostinger.yml restart

prod-logs: ## Show production logs
	docker compose -f docker-compose.hostinger.yml logs -f

prod-build: ## Build production images
	docker compose -f docker-compose.hostinger.yml build

##
## 💻 Development Commands
##

shell: ## Access PHP container shell
	docker compose exec php sh

shell-db: ## Access database container shell
	docker compose exec database psql -U app -d app

shell-frontend: ## Access frontend container shell
	docker compose exec frontend sh

install: ## Install all dependencies (Composer + NPM for backend and frontend)
	docker compose exec php composer install
	docker compose exec node npm install
	docker compose exec frontend npm install

composer-install: ## Install PHP dependencies
	docker compose exec php composer install

npm-install: ## Install Node.js dependencies (backend)
	docker compose exec node npm install

frontend-install: ## Install frontend dependencies
	docker compose exec frontend npm install

build-assets: ## Build frontend assets (Symfony Encore)
	docker compose exec node npm run build

watch-assets: ## Watch and rebuild assets on changes
	docker compose exec node npm run watch

##
## 🗄️  Database Commands
##

db-migrate: ## Run database migrations
	docker compose exec php bin/console doctrine:migrations:migrate --no-interaction

db-reset: ## Reset database (drop, create, migrate)
	docker compose exec php bin/console doctrine:database:drop --force --if-exists
	docker compose exec php bin/console doctrine:database:create
	docker compose exec php bin/console doctrine:migrations:migrate --no-interaction

db-fixtures: ## Load database fixtures (if available)
	docker compose exec php bin/console doctrine:fixtures:load --no-interaction

db-diff: ## Generate new migration from entity changes
	docker compose exec php bin/console doctrine:migrations:diff

db-schema-update: ## Update database schema (dev only)
	docker compose exec php bin/console doctrine:schema:update --force

##
## 🧪 Testing Commands
##

test: backend-test ## Run all tests

backend-test: phpunit behat ## Run all backend tests (PHPUnit + Behat)

phpunit: ## Run PHPUnit tests
	docker compose exec php vendor/bin/phpunit

phpunit-coverage: ## Run PHPUnit tests with coverage
	docker compose exec php vendor/bin/phpunit --coverage-html coverage

behat: ## Run Behat acceptance tests
	docker compose exec php vendor/bin/behat

behat-suite: ## Run specific Behat suite (usage: make behat-suite SUITE=task_management)
	docker compose exec php vendor/bin/behat --suite=$(SUITE)

frontend-test: ## Run frontend Playwright tests
	docker compose exec frontend npm run test

frontend-test-ui: ## Run frontend tests with UI
	docker compose exec frontend npm run test:ui

frontend-test-headed: ## Run frontend tests in headed mode
	docker compose exec frontend npm run test:headed

##
## 🔍 Code Quality Commands
##

lint: ## Run code linting (if php-cs-fixer is installed)
	@docker compose exec php vendor/bin/php-cs-fixer fix --dry-run --diff 2>/dev/null || echo "⚠️  php-cs-fixer not installed. Run 'composer require --dev friendsofphp/php-cs-fixer' to enable linting."

lint-fix: ## Fix code style issues (if php-cs-fixer is installed)
	@docker compose exec php vendor/bin/php-cs-fixer fix 2>/dev/null || echo "⚠️  php-cs-fixer not installed. Run 'composer require --dev friendsofphp/php-cs-fixer' to enable linting."

cache-clear: ## Clear Symfony cache
	docker compose exec php bin/console cache:clear

cache-warmup: ## Warmup Symfony cache
	docker compose exec php bin/console cache:warmup

##
## 📊 Monitoring & Debugging
##

stats: ## Show container resource usage
	docker stats --no-stream

top: ## Show running processes in containers
	docker compose top

inspect-php: ## Inspect PHP container
	docker compose exec php php -v
	docker compose exec php php -i | grep -E "memory_limit|max_execution_time|upload_max_filesize"

inspect-db: ## Show database info
	docker compose exec database psql -U app -d app -c "SELECT version();"
	docker compose exec database psql -U app -d app -c "\l"

##
## 🔧 Utility Commands
##

init: install db-migrate build-assets ## Initialize project (install deps, migrate, build)

rebuild: clean up-build init ## Full rebuild of the project

restart-service: ## Restart specific service (usage: make restart-service SERVICE=php)
	docker compose restart $(SERVICE)

exec: ## Execute command in PHP container (usage: make exec CMD="bin/console debug:router")
	docker compose exec php $(CMD)

# Default target
.DEFAULT_GOAL := help
