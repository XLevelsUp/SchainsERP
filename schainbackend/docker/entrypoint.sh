#!/bin/sh
set -e

# Render (and most PaaS hosts) inject the port to listen on via $PORT.
# Apache's default config listens on 80, so rewrite it at container start.
PORT="${PORT:-80}"
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Config/route caches must be built after env vars are actually present
# (they're injected at container start, not at image build time).
php artisan config:clear
php artisan route:clear

exec apache2-foreground
