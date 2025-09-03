# Stage 1: Composer - PHP Dependencies
FROM composer:2 AS composer_builder

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --prefer-dist

# Stage 2: Node.js 20 - Frontend Build  
FROM node:20-alpine AS frontend_builder

WORKDIR /app
COPY package*.json ./
RUN npm ci

COPY . .
RUN npm run build

# Stage 3: PHP 8.3 Apache - Final Runtime
FROM php:8.3-apache AS production

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    libpq-dev \
    gettext-base \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip

# Enable Apache modules
RUN a2enmod rewrite headers

# Set working directory
WORKDIR /var/www/html

# Copy Laravel application files first
COPY . /var/www/html/

# Copy vendor from composer stage
COPY --from=composer_builder /app/vendor /var/www/html/vendor

# Copy built frontend assets from node stage  
COPY --from=frontend_builder /app/public/build /var/www/html/public/build

# Create Apache vhost template for dynamic port
RUN echo 'ServerName localhost\n\
Listen ${APACHE_PORT}\n\
<VirtualHost *:${APACHE_PORT}>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory "/var/www/html/public">\n\
        AllowOverride All\n\
        Require all granted\n\
        Options -Indexes\n\
    </Directory>\n\
    ErrorLog /var/log/apache2/error.log\n\
    CustomLog /var/log/apache2/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf.template

# Set up Laravel directories and permissions
RUN mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache database \
    && touch database/database.sqlite \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 storage bootstrap/cache \
    && chmod 664 database/database.sqlite

# Copy and prepare startup script  
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Expose port 80 (Railway will map to $PORT)
EXPOSE 80

# Start with custom script
CMD ["/usr/local/bin/start.sh"]