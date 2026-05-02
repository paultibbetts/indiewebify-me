FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
	--no-dev \
	--no-interaction \
	--no-progress \
	--no-scripts \
	--prefer-dist \
	--optimize-autoloader

FROM php:8.5-apache

RUN a2enmod rewrite

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
