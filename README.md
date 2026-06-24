# Grand Duta Estate Management

Grand Duta Estate Management adalah aplikasi estate/property management berbasis Laravel API dan React dashboard untuk mengelola pelanggan, klaster, tagihan IPL, pembayaran, reversal, piutang, laporan, dokumen PDF, audit log, notifikasi, serta integrasi pembayaran manual/Xendit/Midtrans.

## Fitur Utama

- Auth API menggunakan Laravel Sanctum.
- RBAC menggunakan Spatie Permission untuk role `root`, `back_office`, `loket`, dan `cs`.
- Master data klaster dan pelanggan.
- Billing bulanan, billing khusus, billing mundur, approval single dan batch.
- Pembayaran loket, payment gateway abstraction, pembayaran manual, webhook Xendit/Midtrans yang diverifikasi dan idempotent.
- Cicilan, reversal, piutang, dashboard, laporan, dan PDF SPT/SPK/rekap.
- Audit log global hanya untuk `root`.
- React dashboard dengan Ant Design, permission menu, responsive layout, dan light/dark/system theme.

## Struktur

```text
.
├── app/                    # Backend Laravel 12 API
├── config/
├── database/
├── docs/
├── frontend/               # React + Vite dashboard
├── routes/api.php
└── tests/
```

## Instalasi Backend

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve
```

Sample akun lokal:

```text
Root
Username: root
Email: root@grandduta.test
Password: password

Back Office
Username: backoffice
Password: password

Loket
Username: loket
Password: password

Customer Service
Username: cs
Password: password
```

## Instalasi Frontend

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

Default API URL frontend:

```env
VITE_API_BASE_URL=http://localhost:8000/api/v1
```

## Konfigurasi Pembayaran

Provider aktif ditentukan dengan:

```env
PAYMENT_GATEWAY=manual
```

Nilai yang didukung:

- `manual`
- `xendit`
- `midtrans`

Isi credential Xendit/Midtrans hanya di `.env`, bukan di source code. Webhook tersedia di:

```text
POST /api/v1/payments/webhooks/xendit
POST /api/v1/payments/webhooks/midtrans
```

## Perintah Operasional

```bash
php artisan queue:work
php artisan schedule:work
php artisan test
cd frontend && npm run build
```

## Verifikasi Saat Ini

Sudah dijalankan:

```bash
php artisan route:list --path=api/v1
php artisan migrate:fresh --seed --force
php artisan test
cd frontend && npm run build
```

Catatan: test berjalan hijau dengan warning karena file `.env` lokal tidak dibuat di workspace. Gunakan `.env.example` untuk setup lokal.

## Dokumentasi

- OpenAPI ringkas: [docs/openapi.yaml](docs/openapi.yaml)
- Deployment: [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)
