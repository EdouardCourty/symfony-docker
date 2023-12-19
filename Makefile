DKC = docker compose

BCL = php bin/console

PHPUNIT = vendor/bin/phpunit
PHPSTAN = vendor/bin/phpstan
PHPCS   = vendor/bin/phpcs

##
##                     ✨✨✨ The Makefile ✨✨✨
##
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9_-]+:.*?##.*$$)|(^##)' Makefile | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

##🐳 Docker
copy-compose: ## Makes a copy of the docker-compose/yml.dist file
	cp -n docker-compose.yml.dist docker-compose.yml

up: ## Starts the Docker containers
	$(DKC) up --detach --remove-orphans

stop: ## Stops the Docker containers
	$(DKC) stop

down: ## Downs the Docker containers & volumes
	$(DKC) down -v

build: ## Builds the Docker containers
	$(DKC) build

docker-restart: dk-stop ## Restarts the Docker containers
docker-restart: dk-up

##🌐 Project
install: build up vendor dk-reload-database ## Gets the project running from scratch

bash: ## Starts a bash on the PHP server
	$(DKC) exec server bash

vendor: ## Installs the PHP dependencies
	$(DKC) exec server bash -c "composer install"

cc: ## Clears the Symfony cache
	$(DKC) exec server bash -c "$(BCL) cache:clear"

_drop-database:
	$(BCL) doctrine:database:drop --force

_create-database:
	$(BCL) doctrine:database:create

_execute-migrations:
	$(BCL) doctrine:migrations:migrate --no-interaction

_reload-database:
	$(BCL) doctrine:database:drop --force
	$(BCL) doctrine:database:create
	$(BCL) doctrine:migrations:migrate --no-interaction
	$(BCL) doctrine:fixtures:load --no-interaction

reload-database: ## Reloads a clean database from the fixtures
	$(DKC) exec server bash -c "make _reload-database"

migrate: ## Migrates the database to the latest version
	$(DKC) exec server bash -c "make _execute-migrations"

make-mig: ## Creates a new migration from the latest schema changes
	$(DKC) exec server bash -c "$(BCL) make:migration"

##⛩️ CodeStyle & Tests
phpcs: ## Checks the PSR-12 compliance
	$(DKC) exec server bash -c "$(PHPCS) --standard=PSR12 --extensions=php -n src"

phpunit: ## Runs the PHPUnit tests
	$(DKC) exec server bash -c "$(PHPUNIT)"

phpstan: ## Runs the PHPStan
	$(DKC) exec server bash -c "$(PHPSTAN)"

# These line avoid make to confuse argument with target
%:
	@:
