FROM php:8.2-fpm-bullseye


RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    nginx \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    default-libmysqlclient-dev \
    libonig-dev \
    libcurl4-openssl-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql mbstring curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*


RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer


COPY . /var/www/html


WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction --memory-limit=512M


COPY nginx.conf /etc/nginx/nginx.conf


RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html


EXPOSE 80


CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]


