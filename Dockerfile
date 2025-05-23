FROM debian:bullseye-slim

    # Install dependencies and PHP 8.2 from sury.org in one layer
    RUN apt-get update && apt-get install -y --no-install-recommends \
        apt-transport-https ca-certificates software-properties-common lsb-release wget curl git unzip \
        nginx php8.2-fpm php8.2-gd php8.2-mysql php8.2-mbstring php8.2-curl php8.2-xml php8.2-bcmath \
        && echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list \
        && wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg \
        && apt-get update && apt-get upgrade -y \
        && rm -rf /var/lib/apt/lists/* \
        && mkdir -p /run/php && chown www-data:www-data /run/php

    # Install Composer
    RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

    # Set working directory
    WORKDIR /var/www/html

    # Copy project files
    COPY . /var/www/html

    # Set permissions before Composer
    RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

    # Install Composer dependencies
    RUN composer install --no-dev --optimize-autoloader --no-interaction --verbose --ignore-platform-reqs

    # Copy Nginx configuration
    COPY nginx.conf /etc/nginx/nginx.conf

    # Expose port 80 for Render
    EXPOSE 80

    # Start PHP-FPM and Nginx
    CMD ["/bin/sh", "-c", "php-fpm8.2 -D && nginx -g 'daemon off;'"]
