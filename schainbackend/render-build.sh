#!/usr/bin/env bash
# exit on error
set -o errexit

echo "Running composer install..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "Clearing caches..."
php artisan optimize:clear

echo "Running migrations..."
php artisan migrate --force

echo "Generating Passport keys if missing..."
php artisan passport:keys --force
