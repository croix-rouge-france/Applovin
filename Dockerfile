FROM debian:bullseye

# Installation des dépendances système
RUN apt-get update && apt-get install -y \
    apt-transport-https \
    ca-certificates \
    gnupg \
    lsb-release \
    curl \
    wget \
    unzip \
    git \
    nginx \
    && rm -rf /var/lib/apt/lists/*

# Ajout du dépôt PHP de Sury
RUN curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /etc/apt/trusted.gpg.d/php.gpg \
    && echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list

# Installation de PHP 8.2 et des extensions nécessaires
RUN apt-get update && apt-get install -y \
    php8.2 \
    php8.2-fpm \
    php8.2-cli \
    php8.2-gd \
    php8.2-mysql \
    php8.2-mbstring \
    php8.2-curl \
    php8.2-xml \
    php8.2-bcmath \
    php8.2-gmp \
    && rm -rf /var/lib/apt/lists/*

# Installation de Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Définition du répertoire de travail
WORKDIR /var/www/html

# Copie des fichiers de l'application
COPY . /var/www/html

# Installation des dépendances PHP via Composer
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Configuration des permissions
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Copie de la configuration Nginx
COPY nginx.conf /etc/nginx/nginx.conf

# Exposition du port 80
EXPOSE 80

# Commande de démarrage
CMD ["sh", "-c", "php-fpm8.2 -D && nginx -g 'daemon off;'"]


