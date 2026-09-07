#!/bin/bash
set -e

PORT="${PORT:-10000}"

echo "===================================="
echo "Starting Laravel application"
echo "Render PORT: ${PORT}"
echo "===================================="

# Configure Apache to use Render's assigned port
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf

sed -i \
    "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" \
    /etc/apache2/sites-available/000-default.conf

echo "Clearing Laravel caches..."

php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
echo "Running database migrations..."

php artisan migrate --force

echo "Caching Laravel configuration..."

php artisan config:cache

echo "Starting Apache..."

exec apache2-foreground