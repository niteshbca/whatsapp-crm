#!/usr/bin/env bash
set -e

# Prepare the framework caches for production
php artisan config:cache
php artisan route:cache

# Create the SQLite database file if we are using sqlite (Render free postgres is recommended)
php artisan migrate --force

# Link storage so uploaded files are served
php artisan storage:link --force

# Serve the app
php artisan serve --host=0.0.0.0 --port=$PORT
