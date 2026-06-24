# Grandduta State Management System

Sistem manajemen apartemen/perumahan berbasis web yang dibangun dengan Laravel 10. Aplikasi ini menyediakan antarmuka web untuk pengelola dan REST API untuk integrasi mobile, mencakup pengelolaan penghuni, tagihan, pengaduan, perawatan hunian, berita, dan informasi kontak darurat.

---

## Daftar Isi

1. [Gambaran Umum Sistem](#1-gambaran-umum-sistem)
2. [Struktur Project](#2-struktur-project)
3. [Fitur Aplikasi](#3-fitur-aplikasi)
4. [Struktur Database](#4-struktur-database)
5. [Routing dan Endpoint](#5-routing-dan-endpoint)
6. [Konfigurasi Sistem](#6-konfigurasi-sistem)
7. [Autentikasi dan Otorisasi](#7-autentikasi-dan-otorisasi)
8. [Integrasi Pihak Ketiga](#8-integrasi-pihak-ketiga)
9. [Alur Bisnis Sistem](#9-alur-bisnis-sistem)
10. [Instalasi dan Deployment](#10-instalasi-dan-deployment)
11. [Maintenance dan Pengembangan](#11-maintenance-dan-pengembangan)
12. [Lampiran Teknis](#12-lampiran-teknis)

---

## 1. Gambaran Umum Sistem

### Tujuan Aplikasi

Grandduta State Management System adalah platform digital untuk pengelolaan kompleks apartemen atau perumahan (state). Sistem ini membantu manajemen gedung dalam mengelola operasional sehari-hari dan memberikan kanal komunikasi antara pengelola dengan penghuni.

### Fungsi Utama

- Pengelolaan data penghuni dan unit hunian
- Penagihan iuran bulanan dengan integrasi payment gateway
- Pencatatan dan penanganan pengaduan penghuni
- Manajemen permintaan perawatan hunian (homecare)
- Penyebaran informasi/berita kepada penghuni
- Direktori kontak darurat dan instansi penting
- Analitik dan laporan keuangan untuk manajemen

### Ruang Lingkup Penggunaan

| Pengguna | Akses |
|---|---|
| Admin/Staff | Antarmuka web penuh: manajemen semua fitur, laporan, dan konfigurasi |
| Penghuni (Warga) | REST API: akses pengaduan, tagihan, homecare, berita melalui aplikasi mobile |

### Arsitektur Aplikasi

```
┌──────────────────────────────────────────────────────┐
│                     CLIENT LAYER                      │
│  ┌─────────────────┐      ┌──────────────────────┐   │
│  │  Web Browser    │      │   Mobile App (API)   │   │
│  │  (Admin/Staff)  │      │   (Penghuni)         │   │
│  └────────┬────────┘      └──────────┬───────────┘   │
└───────────┼───────────────────────────┼───────────────┘
            │ HTTP + Session            │ HTTP + JWT
┌───────────▼───────────────────────────▼───────────────┐
│                   LARAVEL APPLICATION                  │
│  ┌──────────────┐  ┌───────────────┐  ┌────────────┐  │
│  │ Web Routes   │  │  API Routes   │  │ Middleware │  │
│  │ (Blade View) │  │ (JSON/REST)   │  │ Auth/JWT   │  │
│  └──────┬───────┘  └──────┬────────┘  └────────────┘  │
│         │                 │                            │
│  ┌──────▼─────────────────▼────────────────────────┐  │
│  │              Controllers & Services              │  │
│  └──────────────────────┬──────────────────────────┘  │
│                         │                             │
│  ┌──────────────────────▼──────────────────────────┐  │
│  │                    Models (ORM)                  │  │
│  └──────────────────────┬──────────────────────────┘  │
└─────────────────────────┼──────────────────────────────┘
                          │
┌─────────────────────────▼──────────────────────────────┐
│                    DATA & SERVICE LAYER                  │
│  ┌──────────────┐  ┌───────────────┐  ┌─────────────┐  │
│  │  MySQL DB    │  │  File Storage │  │  Midtrans   │  │
│  │  (Eloquent)  │  │  /public/     │  │  Payment GW │  │
│  └──────────────┘  └───────────────┘  └─────────────┘  │
└──────────────────────────────────────────────────────────┘
```

---

## 2. Struktur Project

### Struktur Folder

```
grandduta-state-management/
├── app/
│   ├── Console/
│   │   └── Kernel.php               # Registrasi scheduled commands
│   ├── Exceptions/
│   │   └── Handler.php              # Global exception handling
│   ├── Exports/                     # Kelas export Excel (Maatwebsite)
│   │   ├── UserExport.php
│   │   ├── HunianExport.php
│   │   ├── BillingExport.php
│   │   ├── KeluhanExport.php
│   │   ├── HomeCareExport.php
│   │   ├── BeritaExport.php
│   │   ├── JenisIuranExport.php
│   │   ├── DaftarKontakExport.php
│   │   └── EmergencyCallExport.php
│   ├── Http/
│   │   ├── Controllers/             # Web controllers (Blade)
│   │   │   ├── Api/                 # API controllers (JSON)
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── UserApi.php
│   │   │   │   ├── KeluhanApi.php
│   │   │   │   ├── BillingApi.php
│   │   │   │   ├── BeritaApi.php
│   │   │   │   ├── HomeCareApi.php
│   │   │   │   ├── DaftarKontakApi.php
│   │   │   │   └── ProfileApi.php
│   │   │   ├── AuthController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── UserController.php
│   │   │   ├── HunianController.php
│   │   │   ├── BillingController.php
│   │   │   ├── KeluhanController.php
│   │   │   ├── HomeCareController.php
│   │   │   ├── BeritaController.php
│   │   │   ├── DaftarKontakController.php
│   │   │   ├── ProfileController.php
│   │   │   ├── JenisIuranController.php
│   │   │   └── ApartmentController.php
│   │   ├── Middleware/
│   │   │   ├── JwtMiddleware.php    # Validasi JWT token untuk API
│   │   │   ├── Authenticate.php
│   │   │   └── VerifyCsrfToken.php
│   │   └── Kernel.php               # Registrasi middleware
│   ├── Imports/
│   │   └── BillingImport.php        # Import tagihan dari Excel
│   ├── Models/
│   │   ├── User.php
│   │   ├── Hunian.php
│   │   ├── HunianUser.php
│   │   ├── Billing.php
│   │   ├── JenisIuran.php
│   │   ├── KeluhanModel.php
│   │   ├── HomeCare.php
│   │   ├── Berita.php
│   │   ├── DaftarKontak.php
│   │   └── Apartment.php
│   └── Providers/
│       └── AppServiceProvider.php
├── config/
│   ├── jwt.php                      # Konfigurasi JWT Auth
│   ├── midtrans.php                 # Konfigurasi payment gateway
│   ├── auth.php                     # Guard dan provider autentikasi
│   ├── cors.php                     # CORS untuk API
│   ├── database.php
│   ├── cache.php
│   ├── session.php
│   └── filesystems.php
├── database/
│   ├── migrations/                  # File migration tabel
│   ├── seeders/
│   │   ├── DatabaseSeeder.php
│   │   └── UsersSeeder.php          # Data admin default
│   └── factories/
├── public/
│   └── uploads/                     # File yang diupload pengguna
│       ├── profile/
│       ├── berita/
│       ├── keluhan/
│       ├── homecare/
│       ├── daftar_kontak/
│       └── logo/
├── resources/
│   ├── views/                       # Blade templates
│   ├── js/                          # JavaScript assets
│   └── css/                         # Stylesheet
├── routes/
│   ├── web.php                      # Route web (session auth)
│   ├── api.php                      # Route API (JWT auth)
│   ├── channels.php
│   └── console.php
├── storage/
├── tests/
├── .env.example
├── composer.json
├── package.json
└── vite.config.js
```

### Alur Kerja Aplikasi

```
Request Masuk
     │
     ▼
Route Dispatcher (web.php / api.php)
     │
     ▼
Middleware Stack
  ├── Web: Session, CSRF, Auth
  └── API: JWT Validation (JwtMiddleware)
     │
     ▼
Controller
  ├── Validasi input
  ├── Interaksi dengan Model (Eloquent ORM)
  ├── Upload file (jika ada)
  └── Panggil layanan eksternal (Midtrans, Excel)
     │
     ▼
Response
  ├── Web: Blade View (HTML)
  └── API: JSON Response
```

### Dependency Utama

| Package | Versi | Fungsi |
|---|---|---|
| `laravel/framework` | ^10.10 | Core framework |
| `tymon/jwt-auth` | ^2.1 | JWT Authentication untuk API |
| `laravel/sanctum` | ^3.3 | API token management |
| `maatwebsite/excel` | ^3.1 | Import/export Excel |
| `midtrans/midtrans-php` | ^2.5 | Payment gateway integration |
| `laravel/pint` | ^1.0 | PHP code style formatter |
| `phpunit/phpunit` | ^10.1 | Unit testing |
| `vite` | ^5.0.0 | Frontend build tool |
| `axios` | ^1.6.4 | HTTP client untuk frontend |

---

## 3. Fitur Aplikasi

### Daftar Fitur

| # | Fitur | Web Admin | API Mobile |
|---|---|---|---|
| 1 | Dashboard & Analitik | ✓ | — |
| 2 | Manajemen Pengguna | ✓ | ✓ (self) |
| 3 | Manajemen Hunian | ✓ | — |
| 4 | Sistem Tagihan | ✓ | ✓ (view) |
| 5 | Payment Gateway | ✓ | ✓ |
| 6 | Manajemen Pengaduan | ✓ | ✓ |
| 7 | Homecare/Perawatan | ✓ | ✓ |
| 8 | Berita/Pengumuman | ✓ | ✓ (view) |
| 9 | Direktori Kontak | ✓ | ✓ (view) |
| 10 | Jenis Iuran | ✓ | — |
| 11 | Pengaturan Apartemen | ✓ | — |
| 12 | Export Excel | ✓ | — |
| 13 | Import Tagihan | ✓ | — |

### Penjelasan Masing-Masing Fitur

#### 1. Dashboard & Analitik
Halaman utama untuk admin dengan visualisasi data:
- Total tagihan bulan berjalan (lunas vs belum bayar)
- Daftar 10 unit dengan tunggakan terbanyak
- Rekapitulasi tunggakan per blok
- Statistik pengaduan per bulan/tahun
- Filter berdasarkan bulan dan tahun

#### 2. Manajemen Pengguna
Pengelolaan akun pengguna sistem:
- CRUD data pengguna (nama, email, password, no. identitas, no. HP, alamat)
- Status pengguna: aktif/tidak aktif
- Status warga: ya/tidak (membedakan penghuni dari staff)
- Upload foto profil
- Relasi pengguna dengan unit hunian
- Export data ke Excel

#### 3. Manajemen Hunian
Pengelolaan unit apartemen/rumah:
- CRUD data unit hunian (nomor, blok, lantai, alamat)
- Status hunian: isi/kosong
- Data pemilik/penghuni
- Relasi many-to-many dengan pengguna
- Export data ke Excel

#### 4. Sistem Tagihan
Pengelolaan iuran dan tagihan:
- Pembuatan tagihan per unit per bulan/tahun
- Komponen tagihan: harga, biaya admin, diskon, denda
- Pembacaan meteran awal dan akhir
- Status pembayaran: Belum Bayar / Lunas
- Pencatatan tanggal pembayaran
- Import tagihan massal dari Excel
- Export laporan ke Excel

#### 5. Payment Gateway (Midtrans)
Integrasi pembayaran online:
- Generate Snap Token untuk setiap tagihan
- Redirect ke halaman pembayaran Midtrans
- Pembayaran tunai (cash payment oleh admin)
- Update status otomatis setelah pembayaran sukses

#### 6. Manajemen Pengaduan (Keluhan)
Sistem tiket pengaduan penghuni:
- CRUD pengaduan dengan judul, laporan, foto
- Departemen yang bertanggung jawab
- Level prioritas: low / normal / high
- Status penanganan: open / process / close
- Relasi ke unit hunian pengadu
- Export data ke Excel

#### 7. Homecare / Perawatan Hunian
Permintaan perawatan dan perbaikan:
- CRUD permintaan dengan deskripsi dan foto
- Kategori perawatan
- Target penyelesaian (due_date)
- Status: open / process / close
- Relasi ke unit hunian
- Export data ke Excel

#### 8. Berita / Pengumuman
Manajemen konten informasi:
- CRUD artikel berita dengan gambar dan tag
- Tampilan homepage (5 berita terbaru via API)
- Export data ke Excel

#### 9. Direktori Kontak Darurat
Database kontak penting:
- CRUD kontak (nama, instansi, nomor, foto)
- Dapat diakses penghuni melalui API
- Export data ke Excel

#### 10. Jenis Iuran
Master data jenis tagihan:
- CRUD jenis iuran (nama, kode layanan)
- Harga dan biaya admin per jenis
- Status aktif/tidak aktif
- Digunakan sebagai referensi saat membuat tagihan

#### 11. Pengaturan Apartemen
Konfigurasi informasi apartemen:
- Nama dan title aplikasi
- Alamat dan deskripsi
- Upload logo
- Manajemen gambar slider/carousel (disimpan sebagai JSON)

---

## 4. Struktur Database

### Daftar Tabel

| Tabel | Deskripsi |
|---|---|
| `users` | Data pengguna sistem (staff dan penghuni) |
| `hunian` | Data unit hunian/apartemen |
| `hunian_user` | Relasi penghuni dengan unit hunian |
| `billing` | Tagihan/invoice per unit hunian |
| `jenis_iuran` | Master data jenis iuran |
| `keluhan` | Pengaduan dari penghuni |
| `homecare` | Permintaan perawatan hunian |
| `berita` | Artikel berita dan pengumuman |
| `daftar_kontak` | Direktori kontak darurat |
| `apartment` | Pengaturan dan informasi apartemen |
| `password_reset_tokens` | Token reset password (Laravel default) |
| `personal_access_tokens` | Token Sanctum (Laravel default) |
| `failed_jobs` | Log job yang gagal (Laravel default) |

### Struktur Field Setiap Tabel

#### Tabel `users`

| Field | Tipe | Keterangan |
|---|---|---|
| `id` | bigint UNSIGNED | Primary Key, Auto Increment |
| `name` | varchar | Nama lengkap pengguna |
| `email` | varchar | Email (unique), digunakan untuk login |
| `password` | varchar | Password ter-hash (bcrypt) |
| `status_warga` | enum('ya','tdk') | Penanda apakah pengguna adalah penghuni |
| `status_user` | enum('aktif','tdk') | Status aktif/nonaktif akun |
| `no_phone` | varchar | Nomor telepon |
| `gender` | varchar | Jenis kelamin |
| `birth_date` | date | Tanggal lahir |
| `no_identity` | varchar | Nomor KTP/identitas |
| `address` | text | Alamat lengkap |
| `photo` | varchar | Path foto profil |
| `created_by` | bigint | ID pengguna yang membuat data |
| `updated_by` | bigint | ID pengguna yang terakhir mengubah |
| `created_at` | timestamp | Waktu dibuat |
| `updated_at` | timestamp | Waktu diperbarui |

#### Tabel `hunian`

| Field | Tipe | Keterangan |
|---|---|---|
| `id` | bigint UNSIGNED | Primary Key, Auto Increment |
| `nomor` | varchar | Nomor unit hunian |
| `blok` | varchar | Blok/tower |
| `lantai` | varchar | Lantai |
| `alamat` | text | Alamat lengkap unit |
| `status_hunian` | enum('isi','kosong') | Status hunian |
| `pemilik` | varchar | Nama pemilik/penghuni |
| `created_by` | bigint | ID pembuat |
| `updated_by` | bigint | ID pengubah terakhir |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

#### Tabel `hunian_user` (Pivot)

| Field | Tipe | Keterangan |
|---|---|---|
| `id` | bigint UNSIGNED | Primary Key |
| `hunian_id` | bigint UNSIGNED | FK → hunian.id |
| `user_id` | bigint UNSIGNED | FK → users.id |
| `created_by` | bigint | ID pembuat |
| `created_at` | timestamp | |

#### Tabel `billing`

| Field | Tipe | Keterangan |
|---|---|---|
| `id` | bigint UNSIGNED | Primary Key |
| `row_number` | integer | Nomor urut baris |
| `hunian_id` | bigint UNSIGNED | FK → hunian.id |
| `blok` | varchar | Blok unit (denormalized) |
| `lantai` | varchar | Lantai unit (denormalized) |
| `nomor_hunian` | varchar | Nomor unit (denormalized) |
| `kode_layanan` | varchar | Kode jenis layanan |
| `jenis_iuran_id` | bigint UNSIGNED | FK → jenis_iuran.id |
| `harga` | decimal | Harga pokok iuran |
| `biaya_admin` | decimal | Biaya administrasi |
| `diskon` | decimal | Diskon yang diberikan |
| `denda` | decimal | Denda keterlambatan |
| `meteran_awal` | decimal | Angka meteran awal periode |
| `meteran_akhir` | decimal | Angka meteran akhir periode |
| `keterangan` | text | Catatan tambahan |
| `bulan` | integer | Bulan tagihan (1-12) |
| `tahun` | integer | Tahun tagihan |
| `snap_token` | varchar | Token Midtrans Snap |
| `status_bayar` | enum('Belum Bayar','Lunas') | Status pembayaran |
| `payment_date` | date | Tanggal pembayaran |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

#### Tabel `jenis_iuran`

| Field | Tipe | Keterangan |
|---|---|---|
| `id` | bigint UNSIGNED | Primary Key |
| `nama` | varchar | Nama jenis iuran |
| `kode_layanan` | varchar | Kode unik layanan |
| `harga` | decimal | Harga standar |
| `biaya_admin` | decimal | Biaya admin standar |
| `status` | enum('Aktif','Tidak Aktif') | Status aktif |
| `created_by` | bigint | ID pembuat |
| `updated_by` | bigint | ID pengubah terakhir |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

#### Tabel `keluhan`

| Field | Tipe | Keterangan |
|---|---|---|
| `id` | bigint UNSIGNED | Primary Key |
| `hunian_id` | bigint UNSIGNED | FK → hunian.id |
| `departement` | varchar | Departemen yang menangani |
| `title` | varchar | Judul pengaduan |
| `laporan` | text | Isi laporan pengaduan |
| `photo` | varchar | Path foto pendukung |
| `status` | enum('open','close','process') | Status penanganan |
| `level` | enum('low','normal','high') | Tingkat prioritas |
| `created_by` | bigint | ID pembuat |
| `updated_by` | bigint | ID pengubah terakhir |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

#### Tabel `homecare`

| Field | Tipe | Keterangan |
|---|---|---|
| `id` | bigint UNSIGNED | Primary Key |
| `hunian_id` | bigint UNSIGNED | FK → hunian.id |
| `title` | varchar | Judul permintaan perawatan |
| `deskripsi` | text | Deskripsi kebutuhan perawatan |
| `photo` | varchar | Path foto dokumentasi |
| `status` | enum('open','close','process') | Status permintaan |
| `category` | varchar | Kategori perawatan |
| `due_date` | date | Target penyelesaian |
| `created_by` | bigint | ID pembuat |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

#### Tabel `berita`

| Field | Tipe | Keterangan |
|---|---|---|
| `id` | bigint UNSIGNED | Primary Key |
| `title` | varchar | Judul berita |
| `isi_berita` | longtext | Konten berita |
| `gambar` | varchar | Path gambar utama |
| `tag` | varchar | Tag/kategori berita |
| `created_by` | bigint | ID pembuat |
| `updated_by` | bigint | ID pengubah terakhir |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

#### Tabel `daftar_kontak`

| Field | Tipe | Keterangan |
|---|---|---|
| `id` | bigint UNSIGNED | Primary Key |
| `nama` | varchar | Nama kontak |
| `instansi` | varchar | Nama institusi/lembaga |
| `nomor` | varchar | Nomor telepon |
| `photo` | varchar | Path foto kontak |
| `created_by` | bigint | ID pembuat |
| `updated_by` | bigint | ID pengubah terakhir |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

#### Tabel `apartment`

| Field | Tipe | Keterangan |
|---|---|---|
| `id` | bigint UNSIGNED | Primary Key |
| `nama` | varchar | Nama apartemen |
| `title_app` | varchar | Judul aplikasi |
| `alamat` | text | Alamat apartemen |
| `deskripsi` | text | Deskripsi apartemen |
| `logo` | varchar | Path file logo |
| `slider` | json | Array path gambar slider |
| `created_by` | bigint | ID pembuat |
| `updated_by` | bigint | ID pengubah terakhir |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### Relasi Antar Tabel

```
users ──────────────── hunian_user ──────────── hunian
  │   (belongsToMany)               (belongsToMany)  │
  │                                                  │
  │                           keluhan ───────────────┤
  │                           (belongsTo hunian)     │
  │                                                  │
  │                           homecare ──────────────┤
  │                           (belongsTo hunian)     │
  │                                                  │
  │                           billing ───────────────┘
  │                           (belongsTo hunian)
  │                                │
  │                                └── jenis_iuran_id ──── jenis_iuran
  │                                    (belongsTo)
  │
  └── created_by (self-reference, tidak sebagai FK formal)
```

### ERD (Entity Relationship Diagram)

```
┌──────────────┐       ┌──────────────┐       ┌─────────────┐
│    USERS     │  1    │  HUNIAN_USER │   M   │   HUNIAN    │
├──────────────┤───────┤──────────────├───────┤─────────────┤
│ id (PK)      │       │ user_id (FK) │       │ id (PK)     │
│ name         │       │ hunian_id(FK)│       │ nomor       │
│ email        │       │ created_by   │       │ blok        │
│ password     │       └──────────────┘       │ lantai      │
│ status_warga │                              │ alamat      │
│ status_user  │                              │ status      │
│ no_phone     │                              │ pemilik     │
│ photo        │                              └──────┬──────┘
└──────────────┘                                     │
                                                     │ 1
                       ┌──────────────┐  M           │
                       │   KELUHAN    ├──────────────┤
                       ├──────────────┤              │
                       │ id (PK)      │              │
                       │ hunian_id(FK)│              │ 1
                       │ departement  │       ┌──────┴──────┐
                       │ title        │       │   BILLING   │
                       │ status       │  M    ├─────────────┤
                       │ level        │       │ id (PK)     │
                       └──────────────┘       │ hunian_id(FK│
                                              │ jenis_id(FK)│
                       ┌──────────────┐       │ harga       │
                       │   HOMECARE   │  M    │ status_bayar│
                       ├──────────────├───────┤ bulan/tahun │
                       │ id (PK)      │       └──────┬──────┘
                       │ hunian_id(FK)│              │ M
                       │ title        │       ┌──────┴──────┐
                       │ status       │       │ JENIS_IURAN │
                       │ due_date     │       ├─────────────┤
                       └──────────────┘       │ id (PK)     │
                                              │ nama        │
┌──────────────┐       ┌──────────────┐       │ kode_layanan│
│   BERITA     │       │ DAFTAR_KONTAK│       │ harga       │
├──────────────┤       ├──────────────┤       └─────────────┘
│ id (PK)      │       │ id (PK)      │
│ title        │       │ nama         │       ┌─────────────┐
│ isi_berita   │       │ instansi     │       │  APARTMENT  │
│ gambar       │       │ nomor        │       ├─────────────┤
│ tag          │       │ photo        │       │ id (PK)     │
└──────────────┘       └──────────────┘       │ nama        │
                                              │ logo        │
                                              │ slider(JSON)│
                                              └─────────────┘
```

---

## 5. Routing dan Endpoint

### Web Routes (`routes/web.php`)

Semua route web dilindungi oleh middleware `auth` (session-based), kecuali route login.

#### Autentikasi Web

| Method | URI | Controller | Deskripsi |
|---|---|---|---|
| GET | `/login` | AuthController@index | Halaman form login |
| POST | `/do-login` | AuthController@doLogin | Proses autentikasi |
| GET | `/logout` | AuthController@doLogout | Logout dan hapus session |

#### Dashboard

| Method | URI | Controller | Deskripsi |
|---|---|---|---|
| GET | `/page/dashboard` | DashboardController@index | Halaman dashboard dengan statistik |

#### Manajemen Pengguna

| Method | URI | Controller | Deskripsi |
|---|---|---|---|
| GET | `/page/user` | UserController@index | Daftar semua pengguna |
| POST | `/page/user` | UserController@store | Tambah pengguna baru |
| GET | `/page/user/{id}/edit` | UserController@edit | Form edit pengguna |
| PUT | `/page/user/{id}` | UserController@update | Simpan perubahan pengguna |
| DELETE | `/page/user/{id}` | UserController@destroy | Hapus pengguna |
| GET | `/page/user/export` | UserController@export | Export Excel |

#### Manajemen Hunian

| Method | URI | Controller | Deskripsi |
|---|---|---|---|
| GET | `/page/hunian` | HunianController@index | Daftar unit hunian |
| POST | `/page/hunian` | HunianController@store | Tambah unit hunian |
| GET | `/page/hunian/{id}/edit` | HunianController@edit | Form edit unit hunian |
| PUT | `/page/hunian/{id}` | HunianController@update | Simpan perubahan |
| DELETE | `/page/hunian/{id}` | HunianController@destroy | Hapus unit hunian |
| GET | `/page/hunian/export` | HunianController@export | Export Excel |

#### Tagihan (Billing)

| Method | URI | Controller | Deskripsi |
|---|---|---|---|
| GET | `/page/billing` | BillingController@index | Daftar tagihan |
| POST | `/page/billing` | BillingController@store | Buat tagihan baru |
| GET | `/page/billing/{id}` | BillingController@show | Detail tagihan |
| GET | `/page/billing/{id}/edit` | BillingController@edit | Form edit tagihan |
| PUT | `/page/billing/{id}` | BillingController@update | Simpan perubahan |
| DELETE | `/page/billing/{id}` | BillingController@destroy | Hapus tagihan |
| POST | `/page/billing/import` | BillingController@import | Import dari Excel |
| GET | `/page/billing/export` | BillingController@export | Export Excel |
| GET | `/page/billing/{id}/initiate` | BillingController@initiatePayment | Inisiasi pembayaran Midtrans |
| POST | `/page/billing/{id}/cash` | BillingController@cashPayment | Bayar tunai (admin) |
| GET | `/page/billing/update-status` | BillingController@updateStatus | Update status bayar |

#### Pengaduan (Keluhan)

| Method | URI | Controller | Deskripsi |
|---|---|---|---|
| GET | `/page/keluhan` | KeluhanController@index | Daftar pengaduan |
| POST | `/page/keluhan` | KeluhanController@store | Buat pengaduan baru |
| GET | `/page/keluhan/{id}/edit` | KeluhanController@edit | Form edit pengaduan |
| PUT | `/page/keluhan/{id}` | KeluhanController@update | Simpan perubahan |
| DELETE | `/page/keluhan/{id}` | KeluhanController@destroy | Hapus pengaduan |
| GET | `/page/keluhan/export` | KeluhanController@export | Export Excel |

#### Homecare

| Method | URI | Controller | Deskripsi |
|---|---|---|---|
| GET | `/page/homecare` | HomeCareController@index | Daftar permintaan homecare |
| POST | `/page/homecare` | HomeCareController@store | Buat permintaan baru |
| GET | `/page/homecare/{id}/edit` | HomeCareController@edit | Form edit |
| PUT | `/page/homecare/{id}` | HomeCareController@update | Simpan perubahan |
| DELETE | `/page/homecare/{id}` | HomeCareController@destroy | Hapus |
| GET | `/page/homecare/export` | HomeCareController@export | Export Excel |

#### Berita

| Method | URI | Controller | Deskripsi |
|---|---|---|---|
| GET | `/page/berita` | BeritaController@index | Daftar berita |
| POST | `/page/berita` | BeritaController@store | Buat berita baru |
| GET | `/page/berita/{id}/edit` | BeritaController@edit | Form edit |
| PUT | `/page/berita/{id}` | BeritaController@update | Simpan perubahan |
| DELETE | `/page/berita/{id}` | BeritaController@destroy | Hapus |
| GET | `/page/berita/export` | BeritaController@export | Export Excel |

#### Lainnya

| Method | URI | Controller | Deskripsi |
|---|---|---|---|
| GET/POST | `/page/daftar-kontak` | DaftarKontakController | CRUD direktori kontak |
| GET/POST | `/page/jenis-iuran` | JenisIuranController | CRUD master jenis iuran |
| GET/POST | `/page/setting-apartment` | ApartmentController | Pengaturan apartemen |
| GET | `/page/profile` | ProfileController@index | Profil pengguna login |
| POST | `/page/profile/update-foto` | ProfileController@updateFoto | Upload foto profil |
| POST | `/page/profile/reset-foto` | ProfileController@resetFoto | Hapus foto profil |
| POST | `/page/profile/password` | ProfileController@updatePassword | Ganti password |

---

### API Routes (`routes/api.php`)

Semua API route (kecuali login) dilindungi oleh middleware `jwt.verify`.

**Base URL:** `/api`

**Headers yang dibutuhkan untuk request terautentikasi:**
```
Authorization: Bearer {jwt_token}
Content-Type: application/json
Accept: application/json
```

#### Autentikasi API

| Method | Endpoint | Deskripsi | Auth |
|---|---|---|---|
| POST | `/api/login` | Login admin/staff, mengembalikan JWT token | — |
| POST | `/api/login-penghuni` | Login penghuni (`status_warga='ya'`), mengembalikan JWT token | — |
| GET | `/api/user-get-by-token` | Validasi token dan ambil data user | JWT |

**Request Body Login:**
```json
{
  "email": "user@example.com",
  "password": "password"
}
```

**Response Login Sukses:**
```json
{
  "success": true,
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "user": { ... }
}
```

#### User API

| Method | Endpoint | Deskripsi |
|---|---|---|
| GET | `/api/user-list` | Daftar semua pengguna |
| GET | `/api/user-by-id/{id}` | Detail pengguna berdasarkan ID |
| POST | `/api/user/update/{id}` | Update data pengguna |
| POST | `/api/user/update-password/{id}` | Ganti password pengguna |
| DELETE | `/api/user/delete/{id}` | Hapus pengguna |

#### Keluhan API

| Method | Endpoint | Deskripsi |
|---|---|---|
| POST | `/api/keluhan-list` | Daftar pengaduan (filter via body) |
| GET | `/api/keluhan-by-id/{id}` | Detail pengaduan |
| POST | `/api/keluhan/add` | Buat pengaduan baru |
| POST | `/api/keluhan/update/{id}` | Update pengaduan |
| DELETE | `/api/keluhan/delete/{id}` | Hapus pengaduan |

#### Billing API

| Method | Endpoint | Deskripsi |
|---|---|---|
| GET | `/api/billing/{hunian}/list` | Daftar tagihan untuk unit hunian |
| GET | `/api/billing/{hunian}` | Tagihan terbaru unit hunian |
| GET | `/api/billing/{hunian}/{month}/{year}/detail` | Detail tagihan bulan tertentu |
| GET | `/api/billing/{nomorHunian}/initiate-payment` | Generate Snap Token Midtrans |
| GET | `/api/update-billing-status` | Update status bayar setelah pembayaran |
| GET | `/api/billing/edit/{id}` | Data tagihan untuk edit |

#### HomeCare API

| Method | Endpoint | Deskripsi |
|---|---|---|
| POST | `/api/homecare-list` | Daftar homecare (filter via body) |
| GET | `/api/homecare-edit/{id}` | Detail homecare |
| POST | `/api/homecare` | Buat permintaan homecare |
| POST | `/api/homecare/{id}` | Update homecare |
| DELETE | `/api/homecare/{id}` | Hapus homecare |

#### Berita API

| Method | Endpoint | Deskripsi |
|---|---|---|
| GET | `/api/berita-list-homepage` | 5 berita terbaru (untuk homepage mobile) |
| GET | `/api/berita-list` | Semua berita |
| GET | `/api/berita-edit/{id}` | Detail berita |
| POST | `/api/berita` | Buat berita baru |
| POST | `/api/berita/{id}` | Update berita |
| DELETE | `/api/berita/{id}` | Hapus berita |

#### Daftar Kontak API

| Method | Endpoint | Deskripsi |
|---|---|---|
| GET | `/api/daftar-kontak-list` | Daftar semua kontak |
| GET | `/api/daftar-kontak-by-id/{id}` | Detail kontak |
| DELETE | `/api/daftar-kontak/delete/{id}` | Hapus kontak |

#### Profile API

| Method | Endpoint | Deskripsi |
|---|---|---|
| POST | `/api/profile/{id}` | Update profil pengguna |

---

## 6. Konfigurasi Sistem

### Requirement Server

| Komponen | Minimum | Rekomendasi |
|---|---|---|
| PHP | 8.1 | 8.2+ |
| MySQL | 5.7 | 8.0+ |
| Composer | 2.0 | 2.6+ |
| Node.js | 18.x | 20.x LTS |
| NPM | 8.x | 10.x |
| Web Server | Apache 2.4 / Nginx 1.18 | Nginx terbaru |
| RAM | 512 MB | 1 GB+ |
| Storage | 2 GB | 10 GB+ (untuk file upload) |

**PHP Extensions yang Dibutuhkan:**
- `ext-pdo`, `ext-pdo_mysql`
- `ext-mbstring`
- `ext-openssl`
- `ext-tokenizer`
- `ext-xml`
- `ext-ctype`
- `ext-json`
- `ext-fileinfo`
- `ext-gd` atau `ext-imagick`
- `ext-zip` (untuk export Excel)

### Konfigurasi Environment (`.env`)

Buat file `.env` berdasarkan `.env.example`:

```ini
# Aplikasi
APP_NAME="Grandduta State Management"
APP_ENV=production
APP_KEY=                   # Generate: php artisan key:generate
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=grandduta_db
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# Cache & Session
CACHE_DRIVER=file
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Queue
QUEUE_CONNECTION=sync

# Log
LOG_CHANNEL=stack
LOG_LEVEL=error

# JWT Authentication
JWT_SECRET=                # Generate: php artisan jwt:secret
JWT_TTL=60                 # Token TTL dalam menit
JWT_REFRESH_TTL=20160      # Refresh TTL (14 hari dalam menit)
JWT_ALGO=HS256

# Midtrans Payment Gateway
MIDTRANS_SERVER_KEY=your_server_key
MIDTRANS_CLIENT_KEY=your_client_key
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_IS_SANITIZED=true
MIDTRANS_IS_3DS=true

# Mail (jika diperlukan)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
```

### Konfigurasi Nginx

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/grandduta/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 10M;
}
```

### Konfigurasi Apache

File `public/.htaccess` sudah disertakan Laravel secara default dan menangani URL rewriting:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

Aktifkan module yang diperlukan:
```bash
a2enmod rewrite
a2enmod headers
```

### Pengaturan Cache, Session, Queue

| Komponen | Driver Default | Keterangan |
|---|---|---|
| Cache | `file` | Disimpan di `storage/framework/cache/` |
| Session | `file` | Disimpan di `storage/framework/sessions/` |
| Queue | `sync` | Proses sinkron (tanpa queue worker) |

Untuk production dengan traffic tinggi, pertimbangkan menggunakan Redis:
```ini
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

---

## 7. Autentikasi dan Otorisasi

### Mekanisme Login

#### Web (Admin/Staff)
1. User mengakses `/login`
2. Mengisi email dan password di form
3. POST ke `/do-login`
4. `AuthController@doLogin` memvalidasi kredensial via `Auth::attempt()`
5. Jika berhasil: session dibuat, redirect ke `/page/dashboard`
6. Jika gagal: redirect kembali ke login dengan pesan error

#### API (Mobile App)
1. Client POST ke `/api/login` atau `/api/login-penghuni`
2. Controller memvalidasi kredensial
3. Khusus `loginWargaApi`: validasi tambahan `status_warga = 'ya'`
4. Jika berhasil: JWT token dikembalikan dalam response
5. Client menyimpan token dan menyertakannya di header `Authorization: Bearer {token}` untuk semua request selanjutnya

### Sistem Role dan Permission

Sistem menggunakan **attribute-based access control** sederhana melalui field di tabel `users`:

| Field | Nilai | Deskripsi |
|---|---|---|
| `status_user` | `aktif` / `tdk` | Menentukan apakah akun dapat digunakan |
| `status_warga` | `ya` / `tdk` | Membedakan penghuni dari staff/admin |

**Logika akses:**
- `status_warga = 'tdk'`: Staff/Admin — akses penuh ke web admin dan API admin
- `status_warga = 'ya'`: Penghuni/Warga — hanya dapat login melalui endpoint `/api/login-penghuni`

> Sistem saat ini tidak memiliki role-based access control (RBAC) granular. Semua staff yang login ke web memiliki akses yang sama.

### Middleware Keamanan

#### `JwtMiddleware` (API)
`app/Http/Middleware/JwtMiddleware.php`

Cara kerja:
1. Ambil token dari header `Authorization: Bearer {token}`
2. Validasi token menggunakan `JWTAuth::parseToken()->authenticate()`
3. Jika valid: request dilanjutkan dengan user yang terotentikasi
4. Jika tidak valid: return JSON error 401

Error yang ditangani:
- `TokenExpiredException` — Token sudah kedaluwarsa
- `TokenInvalidException` — Token tidak valid/dimanipulasi
- `JWTException` — Token tidak ditemukan di header

#### `Authenticate` (Web)
Middleware bawaan Laravel untuk session-based auth. Redirect ke `/login` jika tidak terotentikasi.

#### CSRF Protection
Semua form web dilindungi oleh CSRF token via `VerifyCsrfToken` middleware. Token harus disertakan di setiap POST/PUT/DELETE request dari form HTML.

### Pengelolaan Token JWT

| Konfigurasi | Nilai Default | Deskripsi |
|---|---|---|
| `JWT_TTL` | 60 menit | Masa berlaku access token |
| `JWT_REFRESH_TTL` | 20160 menit (14 hari) | Masa berlaku refresh token |
| `JWT_ALGO` | HS256 | Algoritma signing |

Token disimpan di sisi client (mobile app) dan bersifat stateless — server tidak menyimpan token.

---

## 8. Integrasi Pihak Ketiga

### Midtrans (Payment Gateway)

**Dokumentasi:** https://docs.midtrans.com

**Konfigurasi** (`config/midtrans.php`):
```php
return [
    'server_key'    => env('MIDTRANS_SERVER_KEY'),
    'client_key'    => env('MIDTRANS_CLIENT_KEY'),
    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    'is_sanitized'  => env('MIDTRANS_IS_SANITIZED', true),
    'is_3ds'        => env('MIDTRANS_IS_3DS', true),
];
```

**Alur Pembayaran:**
1. Admin/penghuni memilih tagihan yang akan dibayar
2. Sistem memanggil endpoint `initiatePayment`
3. Midtrans Snap token di-generate dan disimpan ke field `snap_token` pada tabel `billing`
4. Client diarahkan ke halaman pembayaran Midtrans
5. Setelah pembayaran, Midtrans callback ke `/api/update-billing-status`
6. Status tagihan diperbarui menjadi `Lunas` dan `payment_date` dicatat

**Mendapatkan API Key:**
1. Daftar di https://account.midtrans.com
2. Login ke Midtrans Dashboard
3. Ambil Server Key dan Client Key dari menu Settings > Access Keys
4. Untuk development gunakan Sandbox key; untuk production gunakan Production key

---

### Maatwebsite Excel

**Dokumentasi:** https://docs.laravel-excel.com

Digunakan untuk fitur export dan import data dalam format Excel (.xlsx).

**Export:**
```php
return Excel::download(new BillingExport($data), 'billing.xlsx');
```

**Import:**
```php
Excel::import(new BillingImport, $request->file('file'));
```

**Requirement tambahan di php.ini:**
```ini
extension=zip
extension=gd
```

---

### Tymon JWT Auth

**Dokumentasi:** https://jwt-auth.readthedocs.io

**Setup:**
```bash
php artisan jwt:secret   # Generate JWT_SECRET di .env
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
```

**Penggunaan dalam kode:**
```php
// Generate token saat login
$token = JWTAuth::attempt(['email' => $email, 'password' => $password]);

// Ambil user dari token di request berikutnya
$user = JWTAuth::parseToken()->authenticate();

// Invalidasi token saat logout
JWTAuth::invalidate(JWTAuth::getToken());
```

---

## 9. Alur Bisnis Sistem

### Alur Pengelolaan Tagihan

```
Admin membuat Jenis Iuran (Master Data)
           │
           ▼
Admin membuat data Hunian (Unit Apartemen)
           │
           ▼
Admin membuat Tagihan per unit per bulan
(manual input atau import dari Excel)
           │
           ▼
    Status: "Belum Bayar"
           │
    ┌──────┴──────────┐
    │                 │
    ▼                 ▼
Bayar Online      Bayar Tunai
(Midtrans)        (Admin input)
    │                 │
    ▼                 ▼
Generate Snap    Admin set status
Token               "Lunas"
    │
    ▼
Penghuni bayar via
Midtrans platform
    │
    ▼
Callback: status
diperbarui "Lunas"
```

### Alur Penanganan Pengaduan

```
Penghuni submit pengaduan
(via web admin atau API mobile)
           │
           ▼
    Status: "open"
           │
           ▼
Admin/Staff menerima & mulai proses
           │
           ▼
    Status: "process"
           │
           ▼
Masalah terselesaikan
           │
           ▼
    Status: "close"
```

### Alur Homecare

```
Penghuni membuat permintaan perawatan
(via API mobile atau admin web)
           │
           ▼
Status: "open" + due_date ditetapkan
           │
           ▼
Tim maintenance mulai pengerjaan
           │
           ▼
    Status: "process"
           │
           ▼
Pengerjaan selesai
           │
           ▼
    Status: "close"
```

### Use Case Utama

| Use Case | Actor | Alur Singkat |
|---|---|---|
| UC-01: Login Admin | Staff/Admin | Buka `/login` → Isi credentials → Masuk Dashboard |
| UC-02: Login Penghuni (API) | Penghuni | POST `/api/login-penghuni` → Terima JWT token |
| UC-03: Import Tagihan Massal | Admin | Siapkan Excel → Upload via `/page/billing/import` |
| UC-04: Pembayaran Online | Penghuni | Lihat tagihan → Tap bayar → Midtrans → Lunas otomatis |
| UC-05: Submit Pengaduan | Penghuni | POST `/api/keluhan/add` → Status "open" → Admin proses |
| UC-06: Export Laporan | Admin | Klik export di halaman manapun → Download Excel |

---

## 10. Instalasi dan Deployment

### Langkah Instalasi (Development)

#### 1. Clone Repository
```bash
git clone <repository-url> grandduta-state-management
cd grandduta-state-management
```

#### 2. Install PHP Dependencies
```bash
composer install
```

#### 3. Install Node.js Dependencies
```bash
npm install
```

#### 4. Konfigurasi Environment
```bash
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Edit `.env` sesuai konfigurasi lokal (database, Midtrans key, dll).

#### 5. Buat Database
```sql
CREATE DATABASE grandduta_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### 6. Jalankan Migrasi dan Seeder
```bash
php artisan migrate
php artisan db:seed
```

Seeder membuat akun admin default:
- **Email:** `admin@admin.com`
- **Password:** `password`

#### 7. Buat Symlink Storage
```bash
php artisan storage:link
```

#### 8. Build Assets Frontend
```bash
# Development (hot reload)
npm run dev

# Production build
npm run build
```

#### 9. Jalankan Development Server
```bash
php artisan serve
```

Akses aplikasi di `http://localhost:8000/login`.

---

### Deployment ke Production

#### 1. Upload Source Code
```bash
git clone <repository-url> /var/www/grandduta
```

#### 2. Install Dependencies (Production Mode)
```bash
composer install --no-dev --optimize-autoloader
npm install && npm run build
```

#### 3. Konfigurasi .env Production
```bash
cp .env.example .env
# Edit dengan nilai production
APP_ENV=production
APP_DEBUG=false
MIDTRANS_IS_PRODUCTION=true
```

#### 4. Generate Keys
```bash
php artisan key:generate
php artisan jwt:secret
```

#### 5. Migrasi Database
```bash
php artisan migrate --force
php artisan db:seed --force
```

#### 6. Optimasi Cache
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

#### 7. Set Permission File
```bash
chmod -R 755 /var/www/grandduta
chmod -R 775 /var/www/grandduta/storage
chmod -R 775 /var/www/grandduta/bootstrap/cache
chown -R www-data:www-data /var/www/grandduta
```

#### 8. Storage Link
```bash
php artisan storage:link
```

---

### Troubleshooting Umum

| Masalah | Solusi |
|---|---|
| `500 Internal Server Error` | Cek `storage/logs/laravel.log`; pastikan permission `storage/` dan `bootstrap/cache/` adalah 775 |
| `Class not found` | Jalankan `composer dump-autoload` |
| `Token Mismatch (419)` | Clear cache: `php artisan config:clear && php artisan cache:clear` |
| Upload file gagal | Naikkan `upload_max_filesize` dan `post_max_size` di `php.ini` |
| Midtrans error | Verifikasi `MIDTRANS_SERVER_KEY` di `.env`; pastikan IP server ter-whitelist di dashboard Midtrans |
| JWT `Could not create token` | Pastikan `JWT_SECRET` sudah diset via `php artisan jwt:secret` |
| Excel export error | Aktifkan PHP extension `zip` dan `gd` di `php.ini` |
| Database connection error | Verifikasi kredensial DB di `.env`; pastikan MySQL service berjalan |
| Route not found | Jalankan `php artisan route:clear` lalu `php artisan route:cache` |

---

## 11. Maintenance dan Pengembangan

### Struktur Kode yang Perlu Diperhatikan

1. **API Controllers** di `app/Http/Controllers/Api/` — terpisah dari web controllers untuk maintainability
2. **Export classes** di `app/Exports/` — setiap tabel memiliki kelas export terpisah
3. **Model relationships** didefinisikan di masing-masing model — periksa sebelum membuat query kompleks
4. **Midtrans integration** tersebar di `BillingController` dan `BillingApi` — pertimbangkan untuk disatukan ke Service class

### Best Practice Pengembangan

1. **Validasi Input** — Gunakan Form Request classes (`php artisan make:request`) untuk validasi yang kompleks
2. **File Upload** — Simpan di `public/uploads/{entity}/` dengan nama file yang di-hash untuk menghindari konflik
3. **API Response** — Gunakan format response yang konsisten di seluruh API:
   ```json
   {
     "success": true,
     "message": "Keterangan",
     "data": { ... }
   }
   ```
4. **Database Query** — Manfaatkan eager loading (`with()`) untuk menghindari N+1 problem:
   ```php
   // Baik
   Billing::with('jenisIuran', 'hunian')->get();

   // Buruk (N+1)
   Billing::all()->map(fn($b) => $b->jenisIuran);
   ```
5. **Environment** — Jangan pernah hardcode API key atau kredensial; selalu gunakan `.env`

### Area yang Berpotensi Membutuhkan Refactoring

| Area | Masalah | Saran |
|---|---|---|
| File upload | Logic upload foto tersebar di banyak controller | Buat `FileUploadService` atau trait reusable |
| Validasi input | Validasi langsung di controller methods | Pisahkan ke Form Request classes |
| Business logic | Midtrans dan billing calculation di controller | Pindahkan ke dedicated Service classes |
| Tabel `billing` | Field `blok`, `lantai`, `nomor_hunian` diduplikasi dari `hunian` | Gunakan join/view atau hapus denormalisasi |
| API versioning | Belum ada prefix versi | Tambahkan prefix `/api/v1/` |
| Authorization | Tidak ada RBAC granular | Implementasikan Spatie Laravel Permission jika dibutuhkan |

### Panduan Penambahan Fitur Baru

Contoh menambahkan modul **Fasilitas**:

```bash
# 1. Buat migration
php artisan make:migration create_fasilitas_table

# 2. Buat model
php artisan make:model Fasilitas

# 3. Buat web controller
php artisan make:controller FasilitasController --resource

# 4. Buat API controller
php artisan make:controller Api/FasilitasApi

# 5. Buat export class
php artisan make:export FasilitasExport --model=Fasilitas

# 6. Jalankan migration
php artisan migrate
```

Kemudian daftarkan route di `routes/web.php` dan `routes/api.php`, buat Blade views di `resources/views/`, dan uji fitur baru sebelum deploy.

---

## 12. Lampiran Teknis

### Models

| Model | File | Tabel | Relasi Utama |
|---|---|---|---|
| `User` | `app/Models/User.php` | `users` | `belongsToMany(Hunian)` via `hunian_user` |
| `Hunian` | `app/Models/Hunian.php` | `hunian` | `belongsToMany(User)`, `belongsToMany(HomeCare)` |
| `HunianUser` | `app/Models/HunianUser.php` | `hunian_user` | Pivot table |
| `Billing` | `app/Models/Billing.php` | `billing` | `belongsTo(JenisIuran)` |
| `JenisIuran` | `app/Models/JenisIuran.php` | `jenis_iuran` | — |
| `KeluhanModel` | `app/Models/KeluhanModel.php` | `keluhan` | — |
| `HomeCare` | `app/Models/HomeCare.php` | `homecare` | `belongsTo(Hunian)` |
| `Berita` | `app/Models/Berita.php` | `berita` | — |
| `DaftarKontak` | `app/Models/DaftarKontak.php` | `daftar_kontak` | — |
| `Apartment` | `app/Models/Apartment.php` | `apartment` | — |

### Controllers Web

| Controller | Fungsi |
|---|---|
| `AuthController` | Login, logout web; endpoint JWT login API |
| `DashboardController` | Statistik dashboard (billing, keluhan) |
| `UserController` | CRUD pengguna + export Excel |
| `HunianController` | CRUD unit hunian + export Excel |
| `BillingController` | CRUD tagihan, payment Midtrans, import/export Excel |
| `KeluhanController` | CRUD pengaduan + export Excel |
| `HomeCareController` | CRUD homecare + export Excel |
| `BeritaController` | CRUD berita + upload gambar + export Excel |
| `DaftarKontakController` | CRUD direktori kontak + export Excel |
| `ProfileController` | Profil user: edit, upload foto, ganti password |
| `JenisIuranController` | CRUD master jenis iuran + export Excel |
| `ApartmentController` | Konfigurasi nama, logo, slider apartemen |

### Controllers API

| Controller | Fungsi |
|---|---|
| `Api\AuthController` | JWT login admin dan penghuni |
| `Api\UserApi` | CRUD user via JSON API |
| `Api\KeluhanApi` | CRUD keluhan via JSON API |
| `Api\BillingApi` | Billing dan payment via JSON API |
| `Api\BeritaApi` | CRUD berita via JSON API |
| `Api\HomeCareApi` | CRUD homecare via JSON API |
| `Api\DaftarKontakApi` | Read/delete kontak via JSON API |
| `Api\ProfileApi` | Update profil via JSON API |

### Middleware

| Middleware | Lokasi | Fungsi |
|---|---|---|
| `JwtMiddleware` | `Http/Middleware/JwtMiddleware.php` | Validasi JWT token untuk semua API route |
| `Authenticate` | `Http/Middleware/Authenticate.php` | Guard session-based untuk web route |
| `VerifyCsrfToken` | `Http/Middleware/VerifyCsrfToken.php` | Proteksi CSRF untuk web forms |

### Export & Import Classes

| Class | Jenis | Data |
|---|---|---|
| `UserExport` | Export | Data pengguna |
| `HunianExport` | Export | Data unit hunian |
| `BillingExport` | Export | Data tagihan |
| `KeluhanExport` | Export | Data pengaduan |
| `HomeCareExport` | Export | Data homecare |
| `BeritaExport` | Export | Data berita |
| `JenisIuranExport` | Export | Data jenis iuran |
| `DaftarKontakExport` | Export | Data direktori kontak |
| `EmergencyCallExport` | Export | Data kontak darurat |
| `BillingImport` | Import | Import tagihan massal dari Excel |

### Seeders

| Seeder | Fungsi |
|---|---|
| `DatabaseSeeder` | Entry point, memanggil semua seeder |
| `UsersSeeder` | Akun admin default: `admin@admin.com` / `password` |

### Artisan Commands yang Tersedia

```bash
# Key generation
php artisan key:generate          # Generate APP_KEY
php artisan jwt:secret            # Generate JWT_SECRET

# Database
php artisan migrate               # Jalankan semua migration
php artisan migrate:rollback      # Rollback migration terakhir
php artisan migrate:fresh --seed  # Drop semua tabel, migrate ulang, dan seed
php artisan db:seed               # Jalankan semua seeder

# Cache management
php artisan cache:clear           # Hapus cache aplikasi
php artisan config:clear          # Hapus config cache
php artisan route:clear           # Hapus route cache
php artisan view:clear            # Hapus compiled views

# Optimasi production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Storage
php artisan storage:link          # Symlink public/storage → storage/app/public

# Development
php artisan serve                 # Jalankan development server (port 8000)
php artisan tinker                # REPL interaktif Laravel

# Testing
php artisan test                  # Jalankan semua PHPUnit test
```

---

*Dokumentasi ini dihasilkan berdasarkan analisis menyeluruh source code Grandduta State Management System. Untuk pertanyaan lebih lanjut atau kontribusi pengembangan, silakan hubungi tim pengembang.*
