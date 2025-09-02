#!/bin/bash

# Entrypoint script for Docker container
set -e

echo "Starting Crypto Trading Web Dashboard..."
echo "Environment: ${FLASK_ENV:-production}"
echo "Port: ${PORT:-5000}"

# Wait for Redis if specified
if [ -n "$REDIS_HOST" ]; then
    echo "Waiting for Redis at $REDIS_HOST:${REDIS_PORT:-6379}..."
    while ! nc -z "$REDIS_HOST" "${REDIS_PORT:-6379}"; do
        sleep 1
    done
    echo "Redis is ready!"
fi

# Initialize database
echo "Initializing database..."
python -c "
from app import db_manager
print('Database initialized successfully!')
"

# Start the application
echo "Starting web server..."
exec "$@"