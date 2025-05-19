FROM debian:bullseye

# Étape 1 : Installer les dépendances système de base
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

# Étape 2 : Ajouter le dépôt PHP de Sury
RUN echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list \
    && wget -qO /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg

# Étape 3 : Installer PHP 8.2, NGINX et les extensions nécessaires
RUN apt-get update && apt-get install -y \
    nginx \
    php8.2-fpm \
    php8.2-cli \
    php8.2-gd \
    php8.2-pdo-mysql \
    php8.2-mbstring \
    php8.2-curl \
    && rm -rf /var/lib/apt/lists/*

# Étape 4 : Installer Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Étape 5 : Copier le code source de l'application
COPY . /var/www/html

# Étape 6 : Définir le répertoire de travail
WORKDIR /var/www/html

# Étape 7 : Vérification (debug)
RUN ls -al /var/www/html && cat /var/www/html/composer.json

# Étape 8 : Installer les dépendances avec Composer
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs --verbose



# Étape 10 : Appliquer les permissions
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Étape 11 : Exposer le port HTTP
EXPOSE 80

# Étape 12 : Lancer PHP-FPM et NGINX
CMD ["sh", "-c", "php-fpm8.2 -D && nginx -g 'daemon off;'"]


