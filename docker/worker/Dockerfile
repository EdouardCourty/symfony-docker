FROM php:8.2-cli-bullseye

WORKDIR /var/www/worker

RUN apt update && apt install -y \
    libzip-dev \
    libicu-dev \
    zip \
    libssh-dev \
    libcurl3-dev \
    libgmp-dev \
    libpq-dev \
    librabbitmq-dev \
    supervisor \
    make

RUN pecl install redis-6.0.0RC1 amqp

COPY php.ini /usr/local/etc/php/php.ini
COPY opcache.ini /usr/local/etc/php/conf.d

RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql

RUN docker-php-ext-install \
    pdo_pgsql \
    zip \
    bcmath \
    intl \
    curl \
    gmp \
    opcache

RUN docker-php-ext-enable redis amqp

RUN useradd -ms /bin/bash worker
RUN usermod -u 1000 worker

RUN touch /var/log/php-fpm.error.log
RUN touch /var/log/php-fpm.access.log

RUN chown -R worker:worker /var/log/php-fpm.error.log /var/log/php-fpm.access.log

COPY supervisord.conf /etc/supervisord.conf

USER worker

ENTRYPOINT ["/usr/bin/supervisord", "-c",  "/etc/supervisord.conf"]
