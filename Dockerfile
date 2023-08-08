FROM php:8.2-fpm-bullseye

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/project

RUN apt update && apt install -y \
    vim \
    git \
    libzip-dev \
    libicu-dev \
    zip \
    libssh-dev \
    make \
    libcurl3-dev \
    libgmp-dev \
    libpq-dev

RUN pecl install xdebug-3.2.1

COPY docker/php-fpm/php-fpm.conf /usr/local/etc/php-fpm.conf
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d

RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql

RUN docker-php-ext-install \
    pdo_pgsql \
    zip \
    bcmath \
    intl \
    curl \
    gmp \
    opcache

RUN docker-php-ext-enable xdebug

RUN (umask 000; touch /var/log/xdebug.log)

RUN useradd -ms /bin/bash project_user
RUN usermod -u 1000 project_user

RUN touch /var/log/php-fpm.error.log
RUN touch /var/log/php-fpm.access.log

RUN chown -R project_user:project_user /var/log/php-fpm.error.log /var/log/php-fpm.access.log

USER project_user

CMD ["php-fpm"]
