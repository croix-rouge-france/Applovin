FROM debian:bullseye

# Installer les outils pour ajouter le dépôt
RUN apt-get update && apt-get install -y \
    apt-transport-https \
    ca-certificates \
    software-properties-common \
    lsb-release \
    wget \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Ajouter le dépôt Ondřej Surý pour PHP
RUN echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list \
    && wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg

# Installer PHP 8.2 et les extensions nécessaires
RUN apt-get update && apt-get install -y \
    nginx \
    php8.2-fpm \
    php8.2-gd \
    php8.2-mysql \
    php8.2-mbstring \
    php8.2-curl \
    && rm -rf /var/lib/apt/lists/*

# Créer le dossier pour le socket PHP-FPM
RUN mkdir -p /run/php && chown www-data:www-data /run/php

# Installer Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copier le code de l'application
COPY . /var/www/html

# Exécuter l'installation de Composer
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction --verbose

# Configurer Nginx
COPY nginx.conf /etc/nginx/nginx.conf

# Définir les permissions
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Exposer le port
EXPOSE 80

# Démarrer les services
CMD ["sh", "-c", "service php8.2-fpm start && nginx -g 'daemon off;'"]


