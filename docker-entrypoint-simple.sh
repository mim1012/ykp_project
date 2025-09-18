#!/bin/bash
echo "🚀 Starting YKP Dashboard on port ${PORT:-8080}..."

# Update Apache to listen on Railway's PORT
if [ ! -z "$PORT" ]; then
    echo "🔧 Configuring Apache for port $PORT..."
    sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
    sed -i "s/:80/:$PORT/g" /etc/apache2/sites-available/000-default.conf
fi

# Sync Railway environment variables to .env file
if [ -f "sync-env-vars.php" ]; then
    echo "🔄 Syncing environment variables to .env..."
    php sync-env-vars.php || true
fi

# Always fix Filament autoload at runtime (safety check)
if [ -f "fix-filament-autoload.php" ]; then
    echo "🔧 Fixing Filament autoload..."
    php fix-filament-autoload.php || true
fi

# Generate key if not set (suppress Filament errors)
if [ -z "$APP_KEY" ]; then
    echo "🔑 Generating application key..."
    php -r "
        \$env = file_get_contents('.env');
        if (!preg_match('/^APP_KEY=.+/m', \$env)) {
            \$key = 'base64:' . base64_encode(random_bytes(32));
            \$env = preg_replace('/^APP_KEY=.*/m', 'APP_KEY=' . \$key, \$env);
            file_put_contents('.env', \$env);
            echo 'Key generated: ' . \$key . PHP_EOL;
        }
    " || true
fi

# Try migrations (suppress errors)
echo "📦 Running migrations..."
php artisan migrate --force 2>/dev/null || echo "Migrations skipped or already run"

echo "✅ Starting Apache on port ${PORT:-8080}..."
exec apache2-foreground