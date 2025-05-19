FROM php:8.2-fpm-bullseye


RUN apt-get update --fix-missing || apt-get update --fix-missing && \
    apt-get install -y --no-install-recommends \
    build-essential \
    nginx \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    default-libmysqlclient-dev \
    libonig-dev \
    libcurl4-openssl-dev \
    pkg-config \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql mbstring curl openssl json \
    && apt-get purge -y --auto-remove build-essential \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*


RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    composer --version


COPY . /var/www/html

WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction --memory-limit=1G --verbose || \
    composer install --no-dev --optimize-autoloader --no-interaction --memory-limit=1G --verbose


COPY nginx.conf /etc/nginx/nginx.conf


RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html


EXPOSE 80


CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]

