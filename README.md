# Ad Account Generator - Production Setup Guide

A Laravel 12 application for Creating ad accounts in bulk within Facebook Business Managers using the Meta API.

## Requirements

- **PHP**: >= 8.2
- **Database**: MySQL 8.0+ or MariaDB 10.3+
- **Node.js**: >= 18.x
- **Composer**: >= 2.x
- **Web Server**: Nginx or Apache with mod_rewrite

## Installation

### 1. Clone Repository

```bash
cd /var/www
git clone <repository-url> adaccount-generator
cd adaccount-generator
```

### 2. Install Dependencies

```bash
# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Install Node.js dependencies
npm install

# Build frontend assets
npm run build
```

### 3. Set Permissions

```bash
# Set proper ownership (adjust www-data to your web server user)
sudo chown -R www-data:www-data /var/www/adaccount-generator

# Set directory permissions
sudo find /var/www/adaccount-generator -type d -exec chmod 755 {} \;
sudo find /var/www/adaccount-generator -type f -exec chmod 644 {} \;

# Set write permissions for storage and cache
sudo chmod -R 775 storage bootstrap/cache
sudo chgrp -R www-data storage bootstrap/cache
```

## Configuration

### 1. Environment Setup

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 2. Configure Environment Variables

Edit `.env` file with your production settings:

```env
# Application
APP_NAME="Ad Account Generator"
APP_ENV=production
APP_KEY=base64:your-generated-key-here
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=adaccount_generator
DB_USERNAME=your_db_user
DB_PASSWORD=your_secure_password

# Queue Configuration
QUEUE_CONNECTION=database
# For better performance, consider using Redis:
# QUEUE_CONNECTION=redis

# Cache Configuration
CACHE_STORE=database
# For better performance, consider using Redis:
# CACHE_STORE=redis

# Session Configuration
SESSION_DRIVER=database
SESSION_LIFETIME=120

# Redis (if using)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_CACHE_DB=1
REDIS_QUEUE_DB=2

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"

# Meta API Configuration (Facebook Business Manager)
META_API_VERSION='v24.0'

# Ad Account Configuration
MAX_AD_ACCOUNTS_PER_JOB=5000

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=error
LOG_DEPRECATIONS_CHANNEL=null
```

### 3. Optimize Configuration

```bash
# Optimize application
php artisan optimize

# Optimize Filament
php artisan filament:optimize
```

## Database Setup

### 1. Create Database

### 2. Run Migrations

```bash
# Run all migrations
php artisan migrate --force

# Create initial admin user (follow prompts)
php artisan make:filament-user
```

## Queue Worker Setup

This application uses queues for processing Business Manager jobs. Queue workers must be running in production.

### Queue Worker Commands

```bash
# Start a queue worker (run in background or use supervisor)
php artisan queue:work --daemon --sleep=3
# Restart workers after deployment
php artisan queue:restart

# Monitor failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

## Monitoring

### Laravel Pulse

The application includes Laravel Pulse for monitoring. Access it at:

```
https://your-domain.com/admin/pulse
```

### Log Monitoring

View logs via Filament Log Viewer:

```
https://your-domain.com/admin/log-viewer
```

Or manually:

```bash
# View Laravel logs
tail -f storage/logs/laravel.log

# View worker logs
tail -f storage/logs/worker.log

# View nginx logs
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/log/nginx/access.log
```

## Troubleshooting

### Clear All Caches

```bash
php artisan optimize:clear
php artisan filament:optimize:clear
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Permission Issues

```bash
sudo chown -R www-data:www-data /var/www/adaccount-generator
sudo chmod -R 775 storage bootstrap/cache
```

## Performance Optimization

### Use Redis for Better Performance

```bash
# Install Redis
sudo apt install redis-server

# Update .env
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

### Enable OPcache

Edit PHP configuration:

```bash
sudo nano /etc/php/8.2/fpm/php.ini
```

Enable OPcache:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

Restart PHP-FPM:

```bash
sudo systemctl restart php8.2-fpm
```
