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
Root / legacy test
Username: root
Email: root@grandduta.test
Password: password

Super Admin
Username: superadmin
Email: superadmin@example.com
Password: password
Skenario: akses penuh termasuk audit log dan payment gateway setting.

Estate Admin
Username: admin.estate
Email: admin.estate@example.com
Password: password
Skenario: pengelolaan estate, customer, billing, payment setting.

Finance
Username: finance
Email: finance@example.com
Password: password
Skenario: approval tagihan, verifikasi pembayaran manual, laporan.

Back Office
Username: backoffice
Password: password

Loket
Username: loket
Password: password

Customer Service
Username: cs
Password: password

Customer Lunas
Username: customer.al001
Email: customer.paid@example.com
Password: password
Skenario: seluruh tagihan lunas dan histori pembayaran berhasil.

Customer Menunggak
Username: customer.al002
Email: customer.overdue@example.com
Password: password
Skenario: tagihan jatuh tempo, denda, dan dashboard overdue.

Customer Manual Pending
Username: customer.al003
Email: customer.manual.pending@example.com
Password: password
Skenario: pembayaran manual menunggu verifikasi.

Customer Komplain Aktif
Username: customer.al004
Email: customer.complaint@example.com
Password: password
Skenario: komplain aktif dan maintenance baru.

Customer Tanpa Tagihan
Username: customer.al005
Email: customer.nobills@example.com
Password: password
Skenario: akun aktif tanpa invoice aktif.

Customer Manual Ditolak
Username: customer.al006
Email: customer.manual.rejected@example.com
Password: password
Skenario: pembayaran manual ditolak dan upload ulang bukti.

Customer Xendit
Username: customer.al007
Email: customer.xendit@example.com
Password: password
Skenario: histori pembayaran Xendit berhasil.

Customer Midtrans Gagal
Username: customer.al008
Email: customer.midtrans.failed@example.com
Password: password
Skenario: pembayaran Midtrans gagal dan invoice pending approval.

Customer Banyak Notifikasi
Username: customer.al012
Email: customer.notifications@example.com
Password: password
Skenario: badge notifikasi unread besar.
```

Seeder demo membuat 15 cluster, sekitar 450 data customer/unit, ribuan tagihan beberapa periode, histori pembayaran Xendit/Midtrans/manual sandbox, komplain, maintenance, notifikasi, dokumen dummy, dan audit log. Jalankan ulang dengan:

```bash
php artisan migrate:fresh --seed
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
