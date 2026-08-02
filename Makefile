.DEFAULT_GOAL := help

ifeq ($(shell command -v docker-compose 2>/dev/null),)
    DOCKER_COMPOSE := docker compose
else
    DOCKER_COMPOSE := docker-compose
endif

RUN := $(DOCKER_COMPOSE) run --rm

##@ 📢 Help
help: ## Display this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-24s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

##@ 🐋 Setup
setup: build composer-install ## Build the image and install dependencies
build: ## Build (or rebuild) the app image
	@$(DOCKER_COMPOSE) build app

composer-install: ## Run composer install
	@$(RUN) app composer install
composer-require: ## Require a package. Example: make composer-require p=vendor/package
	@$(RUN) app composer require "$(p)"
composer-require-dev: ## Require a dev package. Example: make composer-require-dev p=vendor/package
	@$(RUN) app composer require --dev "$(p)"

##@ 🛠️ Utility
bash: ## Open an interactive shell in the app container
	@$(RUN) app bash
up: ## Start PHP-FPM + nginx (http://localhost:8080)
	@$(DOCKER_COMPOSE) up -d --build app nginx
serve: up ## Alias of `up`

##@ 🧪 Test
test: run-pest ## Run the Pest test suite

run-pest: ## Run Pest
	@$(DOCKER_COMPOSE) run --rm pest
run-arch: ## Run only the architecture tests (tests/Arch)
	@$(RUN) app vendor/bin/pest tests/Arch
run-tests-filter: ## Run Pest filtered by name. Example: make run-tests-filter p='some test name'
	@$(RUN) app vendor/bin/pest --filter "$(p)"
run-tests-retry: ## Re-run only the tests that failed last time
	@$(RUN) app vendor/bin/pest --retry --display-errors -v
test-db: ## Run the tests that hit real Postgres (needs `supabase start`; NOT part of qa)
	@$(RUN) app vendor/bin/pest -c phpunit.db.xml.dist

##@ 🎨 Quality assurance
qa: run-phpstan run-cs-fixer test ## Run the full quality assurance suite

run-phpstan: ## Run PHPStan static analysis
	@$(DOCKER_COMPOSE) run --rm phpstan
run-cs-fixer: ## Check code style (dry-run, does not modify files)
	@$(DOCKER_COMPOSE) run --rm cs-fixer
run-cs-fixer-fix: ## Auto-fix code style violations
	@$(RUN) app vendor/bin/php-cs-fixer fix --allow-risky=yes

##@ 🐋 Docker
down: ## Remove containers, networks and the built image
	@$(DOCKER_COMPOSE) down --remove-orphans --rmi local