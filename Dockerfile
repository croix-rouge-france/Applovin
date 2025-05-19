FROM debian:bullseye


RUN apt-get update && apt-get install -y \
    nginx \
    php8.2-fpm \
    php8.2-gd \
    php8.2-pdo-mysql \
    php8.2-mbstring \
    php8.2-curl \
    php8.2-openssl \
    php8.2-json \
    && rm -rf /var/lib/apt/lists/*


COPY . /var/www/html


COPY nginx.conf /etc/nginx/nginx.conf


RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html


EXPOSE 80


CMD ["sh", "-c", "service php8.2-fpm start && nginx -g 'daemon off;'"]
