# ---- Stage 1: PHP dependencies ----
FROM php:8.4-cli AS deps
RUN apt-get update && apt-get install -y git unzip libpq-dev libzip-dev libxml2-dev libonig-dev \
    && docker-php-ext-install pdo pdo_pgsql mbstring xml bcmath zip sockets \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction

# ---- Stage 2: runtime ----
FROM php:8.4-cli AS runtime
RUN apt-get update && apt-get install -y curl libpq-dev libzip-dev libxml2-dev libonig-dev \
    && docker-php-ext-install pdo pdo_pgsql mbstring xml bcmath zip sockets \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Datadog APM (same approach as 15SOAT-Fase1: tracer inside the Debian image).
RUN curl -sLO https://github.com/DataDog/dd-trace-php/releases/latest/download/datadog-setup.php \
    && php datadog-setup.php --php-bin=all --enable-appsec=false \
    && rm datadog-setup.php

WORKDIR /var/www
COPY --from=deps /var/www .
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views storage/logs \
    && cp .env.example .env && php artisan key:generate --force

EXPOSE 8000
HEALTHCHECK --interval=10s --timeout=5s --start-period=20s --retries=5 CMD curl -f http://localhost:8000/up || exit 1

# K8s overrides the command per Deployment: api (serve), worker (bus:work), scheduler (schedule:work).
CMD ["sh", "-c", "php artisan config:cache && php artisan l5-swagger:generate && php artisan serve --host=0.0.0.0 --port=8000"]
