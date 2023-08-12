DKC = docker compose
BCL = php bin/console

SERVER_BASH = $(DKC) exec server bash
WORKER_BASH = $(DKC) exec worker bash

CMD_ARGS = $(filter-out $@,$(MAKECMDGOALS))

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

bash:
	$(SERVER_BASH)

dk-vendor:
	$(SERVER_BASH)  -c "composer install"

cc:
	$(SERVER_BASH)  -c "$(BCL) cache:clear"

dk-fix-style:
	$(SERVER_BASH) -c "vendor/bin/phpcs --standard=PSR12 --extensions=php -n src"

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
	$(SERVER_BASH) -c "make _reload-database"

install: dk-build
install: dk-up
install: dk-vendor
install: dk-reload-database

dk-migrate:
	$(SERVER_BASH) -c "make _execute-migrations"

phpstan:
	$(SERVER_BASH) -c "vendor/bin/phpstan"

consume: ## Starts a consumer on the given queue
	$(WORKER_BASH) -c "$(BCL) messenger:consume $(CMD_ARGS)"

_start-workers:
	/usr/bin/supervisord -c /var/www/worker/docker/worker/workers.conf

start-workers: ## Starts the workers
	$(WORKER_BASH) -c "make _start-workers"
