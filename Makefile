DKC = docker compose
BCL = php bin/console

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

docker-restart: dk-stop ## Restarts the Docker containers
docker-restart: dk-up

##🌐 Project
bash: ## Starts a bash on the PHP server
	$(DKC) exec server bash

vendor: ## Installs the PHP dependencies
	$(DKC) exec server bash -c "composer install"

cc: ## Clears the Symfony cache
	$(DKC) exec server bash -c "bin/console cache:clear"

_drop-database:
	$(BCL) doctrine:database:drop --force

_create-database:
	$(BCL) doctrine:database:create

_execute-migrations:
	$(BCL) doctrine:migrations:migrate --no-interaction

_load-fixtures:
	$(BCL) doctrine:fixtures:load --no-interaction

_load-database: _create-database
_load-database: _execute-migrations

_reload-database: _drop-database
_reload-database: _load-database
_reload-database: _load-fixtures

dk-reload-database: ## Reloads a clean database from the fixtures
	$(DKC) exec server bash -c "make _reload-database"

install: build ## Gets the project running from scratch
install: up
install: vendor
install: dk-reload-database

migrate: ## Migrates the database to the latest version
	$(DKC) exec server bash -c "make _execute-migrations"

make-mig: ## Creates a new migration from the latest schema changes
	$(DKC) exec server bash -c "$(BCL) make:migration"

##⛩️ CodeStyle & Tests
phpcs: ## Checks the PSR-12 compliance
	$(DKC) exec server bash -c "vendor/bin/phpcs --standard=PSR12 --extensions=php -n src"

phpunit: ## Runs the PHPUnit tests
	$(DKC) exec server bash -c "vendor/bin/phpunit"

phpstan: ## Runs the PHPStan
	$(DKC) exec server bash -c "vendor/bin/phpstan"


##
##⛵ Deployment
setup-ansible: ## Installs ansible
	pip3 install ansible

deploy-setup: ## Setup the production server
	ansible-playbook -i ops/ansible/inventory_root ops/ansible/setup.yml

deploy-production: ## Deploys the app in production
	ansible-playbook -i ops/ansible/inventory ops/ansible/deploy.yml


# These line avoid make to confuse argument with target
%:
	@:
