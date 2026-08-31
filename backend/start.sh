#!/usr/bin/env bash
set -e

# Make the storage + bootstrap/cache directories writable (Docker sometimes
# copies them as root, breaking the Blade view compiler)
mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs
chmod -R 777 storage bootstrap/cache

# Clear stale compiled views/config (avoid permission-created stale caches)
php artisan view:clear || true

# Migrate the database schema (idempotent -- safe on each start)
php artisan migrate --force

# Seed the initial admin/login user (idempotent -- firstOrCreate)
php artisan db:seed --force

# Link storage so uploaded files are served
php artisan storage:link --force

# Serve the app
php artisan serve --host=0.0.0.0 --port=${PORT:-10000}
