#!/bin/bash

# Exit immediately if a command exits with a non-zero status
set -e

echo "=== Memulai Proses Deploy (CleanTrack RS) ==="

# 1. Pull code terbaru dari GitHub
echo ">>> Pulling latest changes from GitHub..."
git pull origin main

# 2. Update Backend (Laravel)
echo ">>> Updating Backend (Laravel)..."
cd backend

# Pastikan file .env ada
if [ ! -f .env ]; then
    echo "WARNING: .env file not found in backend directory! Please create it."
fi

# Install Composer dependencies
composer install --no-dev --optimize-autoloader

# Run database migrations
echo ">>> Running database migrations..."
php artisan migrate --force

# Optimize Laravel cache
echo ">>> Optimizing Laravel cache..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue if configured
php artisan queue:restart || true

cd ..

# 3. Update Frontend (Vite/Vue)
echo ">>> Updating Frontend (Vite/Vue)..."
cd frontend

# Install Node dependencies
npm install

# Build frontend production bundle
echo ">>> Building frontend assets..."
npm run build

cd ..

echo "=== Proses Deploy Selesai dengan Sukses! ==="
