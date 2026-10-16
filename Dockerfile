# syntax=docker/dockerfile:1

# One image for every PHP process: FrankenPHP (Caddy + PHP 8.4) serves HTTP,
# and the same image runs the queue worker and the scheduler.
FROM dunglas/frankenphp:1-php8.4-trixie

RUN install-php-extensions pdo_pgsql intl zip opcache pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Plain HTTP on an unprivileged port. In production TLS is terminated by the
# load balancer / platform in front of the container.
ENV SERVER_NAME=:8000

# Local images keep dev dependencies (Faker is needed by the seeder). Build a
# production image with: docker build --build-arg COMPOSER_DEV=false .
ARG COMPOSER_DEV=true

COPY composer.json composer.lock ./
RUN if [ "$COMPOSER_DEV" = "true" ]; then NO_DEV=""; else NO_DEV="--no-dev"; fi \
    && composer install $NO_DEV --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN if [ "$COMPOSER_DEV" = "true" ]; then NO_DEV=""; else NO_DEV="--no-dev"; fi \
    && composer dump-autoload $NO_DEV --optimize --no-interaction \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

EXPOSE 8000
