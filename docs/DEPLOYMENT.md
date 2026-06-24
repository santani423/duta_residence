# Deployment Guide

## Server Minimum

- PHP 8.2+
- MySQL 8 atau MariaDB kompatibel
- Composer 2
- Node.js 20+
- Redis direkomendasikan untuk cache, queue, dan rate limiting
- Nginx/Apache dengan SSL

## Backend

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan optimize
```

Pastikan `.env` production berisi credential database, mail, payment gateway, `APP_URL`, `FRONTEND_URL`, dan webhook URL.

## Frontend

```bash
cd frontend
npm ci
cp .env.example .env
npm run build
```

Serve `frontend/dist` dari web server atau CDN. Set `VITE_API_BASE_URL` ke URL backend production.

## Queue dan Scheduler

Gunakan Supervisor/systemd untuk:

```bash
php artisan queue:work --tries=3 --timeout=90
```

Scheduler:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

## Payment Webhook

Daftarkan endpoint berikut di provider:

```text
https://api-domain.example.com/api/v1/payments/webhooks/xendit
https://api-domain.example.com/api/v1/payments/webhooks/midtrans
```

Xendit wajib mengirim callback token yang sama dengan `XENDIT_CALLBACK_TOKEN`. Midtrans webhook diverifikasi memakai signature key.

## Rollback

1. Aktifkan maintenance mode: `php artisan down`.
2. Restore release sebelumnya.
3. Restore database dari backup jika migration mengubah struktur kritis.
4. Jalankan `php artisan optimize`.
5. Nonaktifkan maintenance mode: `php artisan up`.
