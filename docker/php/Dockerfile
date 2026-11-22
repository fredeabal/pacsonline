FROM php:8.3-fpm-alpine

# Dependencias del sistema necesarias para CodeIgniter 4, SQLite y manejo de ZIP/DICOM
RUN apk add --no-cache \
    sqlite \
    sqlite-dev \
    icu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    curl-dev \
    oniguruma-dev \
    git \
    unzip

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_sqlite \
        intl \
        zip \
        gd \
        mbstring \
        curl

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Límites adecuados para subir estudios DICOM (.dcm / .zip)
RUN { \
    echo "upload_max_filesize = 512M"; \
    echo "post_max_size = 512M"; \
    echo "memory_limit = 512M"; \
    echo "max_execution_time = 300"; \
} > /usr/local/etc/php/conf.d/pacs-uploads.ini

WORKDIR /var/www/html
