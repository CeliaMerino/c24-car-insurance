# FrankenPHP rather than php -S or nginx + php-fpm.
#
# The built-in PHP server (php -S) handles ONE request at a time unless
# PHP_CLI_SERVER_WORKERS is set. With four partners called concurrently,
# that turns the whole comparison into a queue and every timing test in
# specs/06-testing.md becomes meaningless. FrankenPHP serves concurrent
# requests natively and keeps this to one container per service.

FROM dunglas/frankenphp:php8.3

RUN install-php-extensions intl opcache zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize

# FrankenPHP serves /app/public.
ENV SERVER_NAME=:80

EXPOSE 80
