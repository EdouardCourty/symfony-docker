# Symfony Docker template

### General information about this template

Software / Images versions:
 - PHP: `php:8.1-fpm-bullseye`
 - Nginx: `nginx:1.9.15-alpine`
 - PostgreSQL: `postgres:14.2-alpine`

### Configuration files

The `nginx` configuration is located in `docker/nginx`. <br />
The `php` configuration is located in `docker/php` and `docker/php-fpm`. <br />
The `xdebug` configuration is located `docker/php`. <br />

### Before starting

Before using this template, please rename the docker user in the `Dockerfile`, and the docker workdir in the `Dockerfile` and `docker-compose.yml` file.<br />
The User entity (`App\Entity\User`) has `app_user` as table name, change it to your needs. Avoid `user` as the table name since it's resevred by Postgres and will throw exceptions when trying to interact with the database.<br />


&copy; Edouard Courty - 2022
