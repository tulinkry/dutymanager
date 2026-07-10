# syntax=docker/dockerfile:1

# ---- deps: install all Composer dependencies, incl. dev (needed by the test stage) ----
FROM composer:2 AS deps
WORKDIR /app
COPY . .
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# ---- test: runs the unit test suite. If it fails, the whole build fails. ----
FROM php:8.4-cli AS test
RUN apt-get update && apt-get install -y --no-install-recommends \
		libcurl4-openssl-dev \
		libicu-dev \
		libonig-dev \
	&& docker-php-ext-install -j"$(nproc)" curl mbstring intl \
	&& apt-get clean && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY --from=deps /app /app
RUN vendor/bin/tester tests/ && touch /app/tests/.tests-passed

# ---- runtime-deps: production Composer install (no dev deps) ----
FROM composer:2 AS runtime-deps
WORKDIR /app
# Forces the test stage to build (and pass) before the runtime image can be built.
COPY --from=test /app/tests/.tests-passed /app/tests/.tests-passed
COPY . .
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# ---- runtime: the actual application image ----
FROM php:8.4-apache AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends \
		libcurl4-openssl-dev \
		libicu-dev \
		libonig-dev \
	&& docker-php-ext-install -j"$(nproc)" curl mbstring intl \
	&& apt-get clean && rm -rf /var/lib/apt/lists/* \
	&& a2enmod rewrite

ENV APACHE_DOCUMENT_ROOT=/var/www/html/www
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
	&& sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY --from=runtime-deps /app /var/www/html
RUN mkdir -p /var/www/html/temp/sessions \
	&& chown -R www-data:www-data /var/www/html/log /var/www/html/temp \
	&& echo "session.save_path = /var/www/html/temp/sessions" > /usr/local/etc/php/conf.d/session-path.ini
