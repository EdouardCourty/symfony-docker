DKC = docker compose

BCL = php bin/console

PHPUNIT = vendor/bin/phpunit
PHPSTAN = vendor/bin/phpstan
PHPCS   = vendor/bin/php-cs-fixer

CMD_ARGS = $(filter-out $@,$(MAKECMDGOALS))

##
##                     ✨✨✨ The Makefile ✨✨✨
##
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9_-]+:.*?##.*$$)|(^##)' Makefile | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

##🐳 Docker
up: ## Starts the Docker containers
	$(DKC) up --detach --remove-orphans

stop: ## Stops the Docker containers
	$(DKC) stop

down: ## Downs the Docker containers & volumes
	$(DKC) down -v

build: ## Builds the Docker containers
	$(DKC) build

restart: ## Restarts the Docker containers
	$(DKC) restart

##🌐 Project
install: build up vendor-install reload-database ## Gets the project running from scratch

bash: ## Starts a bash on the PHP server
	$(DKC) exec server ash

vendor-install: ## Installs the PHP dependencies
	$(DKC) exec server ash -c "composer install"

cc: ## Clears the Symfony cache
	$(DKC) exec server ash -c "$(BCL) cache:clear"

_drop-database:
	$(BCL) doctrine:database:drop --force

_create-database:
	$(BCL) doctrine:database:create

_execute-migrations:
	$(BCL) doctrine:migrations:migrate --no-interaction

_reload-database:
	$(BCL) doctrine:database:drop --force --if-exists
	$(BCL) doctrine:database:create
	$(BCL) doctrine:migrations:migrate --no-interaction
	$(BCL) hautelook:fixtures:load --no-interaction

reload-database: ## Reloads a clean database from the fixtures
	$(DKC) exec server ash -c "make _reload-database"

_reload-tests:
	$(BCL) doctrine:database:drop --env=test --force --if-exists
	$(BCL) doctrine:database:create --env=test
	$(BCL) doctrine:migrations:migrate --env=test --no-interaction
	$(BCL) hautelook:fixtures:load --env=test --no-interaction

reload-tests: ## Reloads the test database from the fixtures
	$(DKC) exec server ash -c "make _reload-tests"

migrate: ## Migrates the database to the latest version
	$(DKC) exec server ash -c "make _execute-migrations"

make-mig: ## Creates a new migration from the latest schema changes
	$(DKC) exec server ash -c "$(BCL) make:migration"

##
##⛩️  CodeStyle & Tests
phpcs: ## Checks and fixes the PSR-12 compliance
	$(DKC) exec server ash -c "$(PHPCSFIXER) fix"

phpunit: ## Runs the PHPUnit tests
	$(DKC) exec server ash -c "$(PHPUNIT) $(CMD_ARGS)"

phpstan: ## Runs the PHPStan
	$(DKC) exec server ash -c "$(PHPSTAN) $(CMD_ARGS)"

# These line avoid make to confuse argument with target
%:
	@:
