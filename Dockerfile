FROM debian:bullseye

# Installer les dépendances système
RUN apt-get update && apt-get install -y \
    apt-transport-https \
    ca-certificates \
    software-properties-common \
    lsb-release \
    wget \
    curl \
    unzip \
    gnupg2 \
    && rm -rf /var/lib/apt/lists/*

# Ajouter le dépôt Sury pour PHP 8.2
RUN echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list \
    && wget -qO /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg

# Installer PHP 8.2 et ses extensions
RUN apt-get update && apt-get install -y \
    php8.2 \
    php8.2-fpm \
    php8.2-cli \
    php8.2-gd \
    php8.2-pdo-mysql \
    php8.2-mbstring \
    php8.2-curl \
    php8.2-xml \
    php8.2-bcmath \
    php8.2-zip \
    nginx \
    && rm -rf /var/lib/apt/lists/*

# Installer Composer
RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer

# Copier les fichiers du projet
COPY . /var/www/html

# Définir le répertoire de travail
WORKDIR /var/www/html

# Fixer les permissions
RUN chown -R www-data:www-data /var/www/html

# Installer les dépendances PHP avec Composer
USER www-data
RUN composer install --no-dev --optimize-autoloader --no-interaction
USER root

# Copier la configuration NGINX
COPY nginx.conf /etc/nginx/nginx.conf

# Fixer les permissions finales
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Exposer le port 80
EXPOSE 80

# Démarrer PHP-FPM et NGINX
CMD ["sh", "-c", "php-fpm8.2 -D && nginx -g 'daemon off;'"]


