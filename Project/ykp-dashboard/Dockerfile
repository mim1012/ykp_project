FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs

# Install PHP extensions  
RUN docker-php-ext-install pdo_mysql pdo_sqlite mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory to Laravel project
WORKDIR /app/Project/ykp-dashboard

# Copy all files
COPY . /app/

# Copy .env file
RUN cp .env.example .env

# Install dependencies and build
RUN composer install --no-dev --optimize-autoloader \
    && npm ci \
    && npm run build

# Setup Laravel
RUN php artisan key:generate \
    && mkdir -p database \
    && touch database/database.sqlite \
    && php artisan migrate --force

# Set permissions
RUN chmod -R 755 storage bootstrap/cache

# Expose port
EXPOSE 8080

# Start command
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]