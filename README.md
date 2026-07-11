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

Sample akun lokal (sesuai `database/seeders/AdminUserSeeder.php`, `ResidentSeeder.php`, dan `UnitSeeder.php`):

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

Property Manager
Username: property.manager
Email: property.manager@example.com
Password: password
Skenario: akses baca estate, billing, payment, laporan (read-only).

Loket
Username: loket
Email: loket@grandduta.test
Password: password

Customer Service
Username: cs
Email: cs@grandduta.test
Password: password

Customer Lunas (AL001)
Username: customer.al001
Email: resident.paid@example.com
Password: password
Skenario: seluruh tagihan lunas dan histori pembayaran berhasil.

Customer Menunggak (AL002)
Username: customer.al002
Email: resident.overdue@example.com
Password: password
Skenario: tagihan jatuh tempo, denda, dan dashboard overdue.

Customer Manual Pending (AL003)
Username: customer.al003
Email: resident.manual.pending@example.com
Password: password
Skenario: pembayaran manual menunggu verifikasi.

Customer Komplain Aktif (AL004)
Username: customer.al004
Email: resident.complaint@example.com
Password: password
Skenario: komplain aktif dan maintenance baru.

Customer Tanpa Tagihan (AL005)
Username: customer.al005
Email: resident.nobills@example.com
Password: password
Skenario: akun aktif tanpa invoice aktif.

Customer Manual Ditolak (AL006)
Username: customer.al006
Email: resident.manual.rejected@example.com
Password: password
Skenario: pembayaran manual ditolak dan upload ulang bukti.

Customer Xendit (AL007)
Username: customer.al007
Email: resident.xendit@example.com
Password: password
Skenario: histori pembayaran Xendit berhasil.

Customer Midtrans Gagal (AL008)
Username: customer.al008
Email: resident.midtrans.failed@example.com
Password: password
Skenario: pembayaran Midtrans gagal dan invoice pending approval.

Customer Maintenance Aktif (AL009)
Username: customer.al009
Email: resident.maintenance@example.com
Password: password
Skenario: memiliki maintenance terjadwal dan selesai.

Customer Nonaktif (AL010)
Username: customer.al010
Email: resident.inactive@example.com
Password: password
Skenario: akun nonaktif (is_active = false), tidak bisa login.

Customer Bayar Sebagian (AL011)
Username: customer.al011
Email: resident.partial@example.com
Password: password
Skenario: invoice dengan pembayaran sebagian.

Customer Banyak Notifikasi (AL012)
Username: customer.al012
Email: resident.notifications@example.com
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
