FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install --no-install-recommends --yes curl git libpq-dev libmagickwand-dev unzip \
    && docker-php-ext-install pdo_pgsql \
    && pecl install imagick-3.8.1 \
    && docker-php-ext-enable imagick \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/media.ini /usr/local/etc/php/conf.d/media.ini
COPY docker/php/imagemagick-policy.xml /etc/ImageMagick-6/policy.xml

WORKDIR /app
