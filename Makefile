DKC = docker-compose
BCL = php bin/console

dk-up:
	$(DKC) up --detach

dk-stop:
	$(DKC) stop

dk-down:
	$(DKC) down

dk-build:
	$(DKC) build

dk-restart: dk-stop
dk-restart: dk-up

dk-bash-php:
	$(DKC) exec server bash

dk-bash-mysql:
	$(DKC) exec database bash

dk-vendor:
	$(DKC) exec server bash -c "composer install"

dk-cache-clear:
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

dk-reload-database:
	$(DKC) exec server bash -c "make _reload-database"

install: dk-build
install: dk-up
install: dk-vendor
install: dk-reload-database

dk-migrate:
	$(DKC) exec server bash -c "make _execute-migrations"
