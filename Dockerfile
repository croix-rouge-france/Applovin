FROM debian:bullseye

RUN apt-get update && apt-get install -y \
    apt-transport-https ca-certificates software-properties-common lsb-release wget curl \
    && rm -rf /var/lib/apt/lists/*

RUN echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list \
    && wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg

RUN apt-get update && apt-get install -y \
    nginx php8.2-fpm php8.2-gd php8.2-mysql php8.2-mbstring php8.2-curl php8.2-xml \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /run/php && chown www-data:www-data /run/php

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY . /var/www/html

WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction --verbose

COPY nginx.conf /etc/nginx/nginx.conf

RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["sh", "-c", "service php8.2-fpm start && nginx -g 'daemon off;'"]

