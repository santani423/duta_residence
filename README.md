# Duta Indah Residences

Duta Indah Residences adalah aplikasi estate/property management berbasis Laravel API dan React dashboard untuk mengelola pelanggan, klaster, tagihan IPL, pembayaran, reversal, piutang, laporan, dokumen PDF, audit log, notifikasi, serta integrasi pembayaran manual/Xendit/Midtrans.

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

Semua akun demo di bawah menggunakan password `password` dan login bisa memakai username maupun email. Daftar lengkap mengikuti `database/seeders/AdminUserSeeder.php`, `CollectorSeeder.php`, `SupervisorSeeder.php`, `ResidentSeeder.php`, dan `UnitSeeder.php`.

### Admin & staf internal (`AdminUserSeeder.php`)

```text
Root / legacy test
Username: root
Email: root@grandduta.test
Password: password
Skenario: superuser legacy, seluruh permission (role "root").

Super Admin
Username: superadmin
Email: superadmin@example.com
Password: password
Skenario: akses penuh termasuk audit log dan payment gateway setting.

Estate Admin Utama
Username: admin.estate
Email: admin.estate@example.com
Password: password
Skenario: pengelolaan estate, customer, billing, payment setting.

Estate Admin Operasional
Username: admin.estate2
Email: admin.estate2@example.com
Password: password
Skenario: sama seperti Estate Admin Utama, untuk uji multi-admin.

Finance
Username: finance
Email: finance@example.com
Password: password
Skenario: approval tagihan, verifikasi pembayaran manual, laporan.

Finance Collection
Username: finance2
Email: finance2@example.com
Password: password
Skenario: sama seperti Finance, untuk uji multi-user finance.

Property Manager
Username: property.manager
Email: property.manager@example.com
Password: password
Skenario: akses baca estate, billing, payment, laporan (read-only).

Property Manager Timur
Username: property.manager2
Email: property.manager2@example.com
Password: password
Skenario: sama seperti Property Manager, area/uji kedua.

Loket
Username: loket
Email: loket@grandduta.test
Password: password
Skenario: input pembayaran manual di loket, buat customer baru, ajukan reversal/cicilan.

Customer Service
Username: cs
Email: cs@grandduta.test
Password: password
Skenario: kelola data penghuni, dokumen, penghuni tambahan, dan emergency alert.
```

Selain itu ada akun berpola berulang (index 1-3) untuk role yang sama, semuanya password `password`:

| Role | Username | Email | Skenario |
| --- | --- | --- | --- |
| Staff Operasional | `ops1`, `ops2`, `ops3` | `ops{n}@example.com` | Lihat unit/cluster/billing, generate dokumen, acknowledge emergency alert. |
| Security | `security1`, `security2`, `security3` | `security{n}@example.com` | Lihat penghuni/unit/kendaraan/occupant, acknowledge emergency alert. |
| Technician | `technician1`, `technician2`, `technician3` | `technician{n}@example.com` | Lihat data penghuni/unit/cluster untuk kebutuhan maintenance. |
| Customer Service | `cs1`, `cs2`, `cs3` | `cs{n}@example.com` | Sama seperti akun `cs` di atas, untuk uji multi-user CS. |

Akun vendor (role `vendor`, akses read-only unit/cluster/penghuni untuk kebutuhan pekerjaan lapangan):

| Username | Email |
| --- | --- |
| `cleaning` | cleaning@vendor.example.com |
| `security.vendor` | security.vendor@vendor.example.com |
| `garden` | garden@vendor.example.com |
| `construction` | construction@vendor.example.com |
| `waste` | waste@vendor.example.com |

### Kolektor - modul penagihan (`CollectorSeeder.php`)

Semua password `password`. Setiap akun sengaja punya `account_status` berbeda supaya bisa menguji setiap skenario login:

| Username | Email | Status | Login |
| --- | --- | --- | --- |
| `kolektor.budi` | kolektorbudi@grandduta.test | active | Berhasil, target tercapai. |
| `kolektor.siti` | kolektorsiti@grandduta.test | active | Berhasil, target tercapai. |
| `kolektor.ahmad` | kolektorahmad@grandduta.test | active | Berhasil, target belum tercapai. |
| `kolektor.dewi` | kolektordewi@grandduta.test | active | Berhasil, target belum tercapai. |
| `kolektor.rudi` | kolektorrudi@grandduta.test | inactive | Ditolak (403). |
| `kolektor.maya` | kolektormaya@grandduta.test | leave (cuti) | Ditolak (403). |
| `kolektor.eko` | kolektoreko@grandduta.test | suspended | Ditolak (403). |

### Supervisor - modul pengawasan (`SupervisorSeeder.php`)

Semua password `password`:

| Username | Email | Status | Wilayah yang diawasi |
| --- | --- | --- | --- |
| `supervisor.rina` | supervisorrina@grandduta.test | active | Cluster Dolomite & Garnet (wilayah Budi & Siti). |
| `supervisor.hendra` | supervisorhendra@grandduta.test | active | Cluster Jade & Amber (wilayah Ahmad & Dewi). |
| `supervisor.wati` | supervisorwati@grandduta.test | inactive | Cluster Diamond. |

### Customer / penghuni (`ResidentSeeder.php` & `UnitSeeder.php`)

Unit dan penghuni diimpor dari data pelanggan sistem lama di `database/data/pelanggan.csv` (parser: `database/seeders/PelangganData.php`), cluster dari `database/data/clusters.csv`. Hasil impor: ±1.440 unit di 14 cluster. Baris uji/contoh dari sistem lama dan unit duplikat (cluster/blok/nomor sama) dilewati; daftarnya dicetak saat seeding.

- Unit tanpa pemilik (nama "0"/kosong) diimpor dengan status `RK` (properti kosong) tanpa penghuni.
- Setiap unit berpemilik mendapat satu penghuni (id `RS` + kode unit, mis. `RS001002`) dan akun customer dengan username `customer.{kode unit}` dan password `password`. Unit `07012` adalah pengecualian legacy dengan username `customer`.
- Tagihan dan pembayaran IPL diimpor hanya dari buku IPL `docs/final_revisi_1.md` (parser: `database/seeders/IplData.php`; pembayaran yang tidak ada di file itu tidak diimpor): 8.383 kuitansi asli yang membayar periode sejak Januari 2024 (tanggal, nominal, kasir, cara bayar; nomor kwitansi manual ada di catatan kuitansi). Kolom "Bulan Tagihan" diurai menjadi bulan-bulan yang dibayar ("Mart-24", "Nov-Des 23", "Des-23 - Feb-24") dan nominalnya dibagi rata per bulan. Unit aktif di cluster 05-09 dan 11-14 mendapat tagihan Belum Bayar untuk bulan sejak Januari 2024 yang belum tercatat dibayar. Cluster 01-04 dan 10 tidak dibuatkan tunggakan karena hampir tidak ada pembayaran lagi di buku sejak 2017/2018. Riwayat kenaikan tarif cluster (Diamond, Chrysocolla, ruko Amber/Alexandrite/Jade Arcade) mengikuti data itu. Unit `07012` tercatat bayar sampai April 2026 sehingga menunggak sejak Mei 2026, dan dipakai test pembayaran loket.
- Komplain, maintenance, notifikasi, dan penugasan kolektor tetap data demo yang ditempelkan ke unit-unit asli di atas.

> **Perhatian:** CSV ini berisi data pribadi asli (nama, nomor HP, alamat KTP), dan semua akun customer hasil seeding memakai password `password`. Jangan jalankan seeder ini di server yang bisa diakses publik tanpa mengganti password tersebut.

Jalankan ulang dengan:

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
