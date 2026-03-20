# SalamPay Deployment Guide

## Overview

Deploy SalamPay to the EC2 server alongside salamticket.net.

| Item | Value |
|------|-------|
| Server | EC2 (same as salamticket.net) |
| Domain | salampay.com, api.salampay.com |
| Web Server | Apache2 |
| PHP | 8.3 |
| Database | MySQL (local) |
| Deploy Path | /var/www/salampay |

## Pre-Deployment Checklist

### 1. DNS Configuration

Add these DNS records for salampay.com:

```
A     salampay.com       → <EC2_PUBLIC_IP>
A     api.salampay.com   → <EC2_PUBLIC_IP>
CNAME www.salampay.com   → salampay.com
```

### 2. Server Preparation

SSH into the EC2 server:

```bash
ssh -i your-key.pem ubuntu@<EC2_IP>
```

Verify required services:

```bash
# Check PHP version
php -v  # Should be 8.3.x

# Check Apache2
systemctl status apache2

# Check MySQL
systemctl status mysql

# Check Redis (optional but recommended)
systemctl status redis-server
```

### 3. Install PHP 8.3 (if not installed)

```bash
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml \
    php8.3-bcmath php8.3-curl php8.3-zip php8.3-gd php8.3-redis
```

## Deployment Steps

### Step 1: Create Database

```bash
# Upload and run the database setup script
scp -i your-key.pem deploy/setup-database.sh ubuntu@<EC2_IP>:/tmp/
ssh -i your-key.pem ubuntu@<EC2_IP> "bash /tmp/setup-database.sh"
```

Or manually:

```sql
-- Connect to MySQL as root
mysql -u root -p

-- Create database
CREATE DATABASE salampay CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user (use same credentials as salamticket for simplicity)
CREATE USER 'salampay_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON salampay.* TO 'salampay_user'@'localhost';
FLUSH PRIVILEGES;
```

### Step 2: Setup Directory Structure

```bash
ssh -i your-key.pem ubuntu@<EC2_IP>

# Create directories
sudo mkdir -p /var/www/salampay/{releases,shared}
sudo mkdir -p /var/www/salampay/shared/storage/{app/public,framework/{cache,sessions,views},logs}
sudo chown -R ubuntu:www-data /var/www/salampay
sudo chmod -R 775 /var/www/salampay/shared/storage
```

### Step 3: Configure Environment

```bash
# Copy .env template to shared directory
sudo nano /var/www/salampay/shared/.env
```

Copy contents from `deploy/.env.production` and update:

- `APP_KEY` - Generate with: `php artisan key:generate --show`
- `DB_USERNAME` - Copy from salamticket .env
- `DB_PASSWORD` - Copy from salamticket .env
- `WAVE_API_KEY` - Get from Wave Merchant Dashboard
- `WAVE_WEBHOOK_SECRET` - Get from Wave Merchant Dashboard

### Step 4: Configure Apache2

```bash
# Copy Apache config
sudo nano /etc/apache2/sites-available/salampay.conf
# Paste contents from deploy/apache/salampay.conf

# Enable site
sudo a2ensite salampay.conf

# Enable required modules
sudo a2enmod rewrite
sudo a2enmod ssl
sudo a2enmod headers
sudo a2enmod proxy_fcgi

# Test configuration
sudo apache2ctl configtest

# Reload Apache
sudo systemctl reload apache2
```

### Step 5: SSL Certificate (Let's Encrypt)

```bash
# Install certbot if not present
sudo apt install certbot python3-certbot-apache

# Get certificate
sudo certbot --apache -d salampay.com -d www.salampay.com -d api.salampay.com

# Verify auto-renewal
sudo certbot renew --dry-run
```

### Step 6: Initial Deployment

```bash
# Clone repository
cd /var/www/salampay
git clone --depth 1 https://github.com/cherifsarr/salampay.git releases/initial
cd releases/initial/backend

# Install dependencies
composer install --no-dev --optimize-autoloader

# Link shared directories
rm -rf storage
ln -s /var/www/salampay/shared/storage storage
ln -s /var/www/salampay/shared/.env .env

# Generate app key (if not already in .env)
php artisan key:generate

# Run migrations
php artisan migrate --force

# Run seeders (first time only)
php artisan db:seed --class=AccountingSeeder --force

# Cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Create storage link
php artisan storage:link

# Set permissions
sudo chown -R ubuntu:www-data .
sudo chmod -R 755 .
sudo chmod -R 775 /var/www/salampay/shared/storage

# Create current symlink
ln -sfn /var/www/salampay/releases/initial /var/www/salampay/current

# Reload services
sudo systemctl reload php8.3-fpm
sudo systemctl reload apache2
```

### Step 7: Configure Scheduler (Cron)

```bash
# Edit crontab
sudo crontab -e

# Add this line:
* * * * * cd /var/www/salampay/current/backend && php artisan schedule:run >> /dev/null 2>&1
```

### Step 8: Configure Queue Worker (Supervisor)

```bash
# Install supervisor if not present
sudo apt install supervisor

# Create config
sudo nano /etc/supervisor/conf.d/salampay-worker.conf
```

Add:

```ini
[program:salampay-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/salampay/current/backend/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=ubuntu
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/salampay/shared/storage/logs/worker.log
stopwaitsecs=3600
```

Start supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start salampay-worker:*
```

## Subsequent Deployments

Use the deployment script:

```bash
# On local machine, push changes to main branch
git push origin main

# On server
ssh -i your-key.pem ubuntu@<EC2_IP>
cd /var/www/salampay
bash deploy/deploy.sh
```

Or use the deployment script directly:

```bash
scp -i your-key.pem deploy/deploy.sh ubuntu@<EC2_IP>:/var/www/salampay/
ssh -i your-key.pem ubuntu@<EC2_IP> "bash /var/www/salampay/deploy.sh"
```

## Directory Structure (After Deployment)

```
/var/www/salampay/
├── current -> releases/20240320123456  # Symlink to current release
├── releases/
│   ├── 20240320123456/                  # Release directories
│   └── 20240319101010/
└── shared/
    ├── .env                              # Environment config
    └── storage/                          # Persistent storage
        ├── app/public/
        ├── framework/
        │   ├── cache/
        │   ├── sessions/
        │   └── views/
        └── logs/
```

## Verification

After deployment:

```bash
# Check application
curl -I https://salampay.com

# Check API
curl https://api.salampay.com/api/health

# Check logs
tail -f /var/www/salampay/shared/storage/logs/laravel.log

# Check supervisor workers
sudo supervisorctl status salampay-worker:*
```

## Rollback

To rollback to a previous release:

```bash
# List releases
ls -la /var/www/salampay/releases/

# Switch to previous release (replace with actual release directory)
ln -sfn /var/www/salampay/releases/20240319101010 /var/www/salampay/current

# Reload services
sudo systemctl reload php8.3-fpm
sudo systemctl reload apache2
```

## Troubleshooting

### 500 Internal Server Error

```bash
# Check Laravel logs
tail -f /var/www/salampay/shared/storage/logs/laravel.log

# Check Apache logs
tail -f /var/log/apache2/salampay_error.log

# Check permissions
ls -la /var/www/salampay/current/backend/
ls -la /var/www/salampay/shared/storage/
```

### Database Connection Issues

```bash
# Test MySQL connection
mysql -u salampay_user -p -e "SHOW DATABASES;"

# Verify .env settings
cat /var/www/salampay/shared/.env | grep DB_
```

### Queue Issues

```bash
# Check supervisor status
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart salampay-worker:*

# Check worker logs
tail -f /var/www/salampay/shared/storage/logs/worker.log
```
