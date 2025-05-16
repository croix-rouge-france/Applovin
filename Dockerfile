FROM php:8.2-fpm
RUN apt-get update && apt-get install -y \
    nginx \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql mbstring openssl ctype json
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
WORKDIR /var/www/html
COPY . .
RUN if [ -f composer.json ]; then composer install --no-dev --optimize-autoloader; fi
RUN if [ -d phpmyadmin ] && [ -f phpmyadmin/composer.json ]; then cd phpmyadmin && composer install --no-dev --optimize-autoloader; fi
COPY ./nginx.conf /etc/nginx/sites-available/default
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html
RUN echo "display_errors = On" >> /usr/local/etc/php/php.ini \
    && echo "error_reporting = E_ALL" >> /usr/local/etc/php/php.ini
EXPOSE 80
CMD service nginx start && php-fpm
