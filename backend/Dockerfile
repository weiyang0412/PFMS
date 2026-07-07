FROM php:8.2-cli

WORKDIR /app

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libonig-dev \
        libzip-dev \
        libxml2-dev \
        libcurl4-openssl-dev \
    && docker-php-ext-install \
        bcmath \
        curl \
        intl \
        mbstring \
        pdo_mysql \
        xml \
        xmlwriter \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . /app
COPY start-container.sh /usr/local/bin/start-container
RUN chmod +x /usr/local/bin/start-container
RUN mkdir -p /app/storage/framework/cache/data \
    /app/storage/framework/sessions \
    /app/storage/framework/views \
    /app/storage/logs \
    && chmod -R 775 /app/storage /app/bootstrap/cache

RUN php artisan optimize:clear || true

RUN composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/start-container"]
