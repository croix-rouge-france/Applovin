FROM php:8.2-fpm-bullseye

# Mettre à jour les dépôts et installer les dépendances nécessaires
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    nginx \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libmariadb-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql mbstring \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Installer Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copier le code de l'application
COPY . /var/www/html

# Installer les dépendances Composer
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader

# Configurer Nginx
COPY nginx.conf /etc/nginx/nginx.conf

# Définir les permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Exposer le port utilisé par Render
EXPOSE 80

# Lancer Nginx et PHP-FPM
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
