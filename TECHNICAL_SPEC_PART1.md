# GRANDDUTA — Technical Design Document & API Specification
## Laravel 12 API-Only Rebuild

**Versi:** 1.0.0  
**Tanggal:** 2026-06-02  
**Status:** Draft  
**Dibuat untuk:** Tim Developer Grand Duta  

---

## Daftar Isi

1. [Gambaran Umum Sistem](#1-gambaran-umum-sistem)
2. [Analisis Kebutuhan](#2-analisis-kebutuhan)
3. [Arsitektur Aplikasi](#3-arsitektur-aplikasi)
4. [Struktur Direktori & Standar Pengembangan](#4-struktur-direktori--standar-pengembangan)
5. [Desain Database](#5-desain-database)
6. [Komponen Aplikasi Laravel](#6-komponen-aplikasi-laravel)
7. [Modul & Fitur Aplikasi](#7-modul--fitur-aplikasi)
8. [Alur Bisnis (Business Flow)](#8-alur-bisnis-business-flow)
9. [Autentikasi & Otorisasi](#9-autentikasi--otorisasi)
10. [Sistem Role & Permission (RBAC)](#10-sistem-role--permission-rbac)
11. [Standar Format Request & Response API](#11-standar-format-request--response-api)
12. [Standar Validasi Data](#12-standar-validasi-data)
13. [Standar Penanganan Error & Exception](#13-standar-penanganan-error--exception)
14. [Logging, Monitoring & Audit Trail](#14-logging-monitoring--audit-trail)
15. [Caching & Optimasi Performa](#15-caching--optimasi-performa)
16. [Upload & Manajemen File](#16-upload--manajemen-file)
17. [Integrasi Pihak Ketiga](#17-integrasi-pihak-ketiga)
18. [Dokumentasi API Lengkap](#18-dokumentasi-api-lengkap)
19. [Pengujian Aplikasi](#19-pengujian-aplikasi)
20. [Deployment & CI/CD](#20-deployment--cicd)
21. [Strategi Backup & Recovery](#21-strategi-backup--recovery)

---

## 1. Gambaran Umum Sistem

### 1.1 Deskripsi Sistem

**GRANDDUTA** adalah sistem informasi manajemen properti dan penagihan untuk perumahan Grand Duta — kompleks perumahan berskala besar yang terdiri dari **14 klaster**, ratusan unit properti, dan berbagai tipe kepemilikan. Sistem ini dibangun ulang menggunakan **Laravel 12 dengan arsitektur API-Only (RESTful)**, menggantikan implementasi lama berbasis CodeIgniter 2.x dengan session-based web app.

Sistem baru berperan sebagai **backend API** yang dapat dikonsumsi oleh berbagai frontend client (web app, mobile app, portal pelanggan) maupun integrasi sistem pihak ketiga.

### 1.2 Lingkup Sistem

```
┌─────────────────────────────────────────────────────────────────────┐
│                     GRANDDUTA API v2 (Laravel 12)                   │
│                                                                     │
│  Client Layer         API Layer              Data Layer             │
│  ─────────────        ─────────────────      ────────────────       │
│  Web App (React) ──►  Laravel 12 API    ──►  MySQL 8.x              │
│  Mobile App      ──►  (RESTful/JSON)    ──►  Redis Cache            │
│  Portal Pelanggan──►  Sanctum Auth      ──►  File Storage           │
│  3rd Party       ──►  RBAC Middleware   ──►  Queue (Redis)          │
│                                                                     │
│  Modul Utama:                                                       │
│  [Auth] [Master Data] [Penagihan] [Pembayaran] [Laporan] [Dokumen]  │
└─────────────────────────────────────────────────────────────────────┘
```

### 1.3 Aktor Sistem

| Role | Kode | Deskripsi |
|------|------|-----------|
| Root / Administrator | `root` | Akses penuh, manajemen user, approval akhir |
| Back Office | `back_office` | Penagihan, approval, laporan, master data |
| Loket / Kasir | `loket` | Transaksi pembayaran, cetak dokumen |
| Customer Service | `cs` | Read-only: info pelanggan & tagihan |

### 1.4 Perbedaan Utama dari Sistem Lama

| Aspek | Sistem Lama (CI) | Sistem Baru (Laravel 12) |
|-------|-----------------|--------------------------|
| Arsitektur | MVC Full-Stack | API-Only (JSON) |
| Autentikasi | Session + MD5 | Laravel Sanctum (Token Bearer) |
| Password | MD5 (tidak aman) | bcrypt (password_hash) |
| Otorisasi | Manual cek level | Spatie Laravel Permission |
| Database | MySQL 5.x (MyISAM) | MySQL 8.x (InnoDB, FK constraint) |
| ORM | CodeIgniter Active Record | Eloquent ORM |
| Validasi | CI form_validation | Laravel Form Request |
| PDF | FPDF + custom wrapper | Laravel-DOMPDF / Snappy |
| Cache | Tidak ada | Redis + Laravel Cache |
| Queue | Tidak ada | Laravel Queue (Redis) |
| Testing | Tidak ada | PHPUnit + Pest |
| API Docs | Tidak ada | OpenAPI/Swagger |

---

## 2. Analisis Kebutuhan

### 2.1 Kebutuhan Bisnis

| ID | Kebutuhan | Prioritas |
|----|-----------|-----------|
| BR-01 | Sistem harus mengelola data pelanggan (CRUD) beserta informasi properti | Tinggi |
| BR-02 | Sistem harus mengelola 14 klaster dengan tarif masing-masing | Tinggi |
| BR-03 | Sistem harus mampu generate tagihan bulanan massal untuk semua pelanggan aktif | Tinggi |
| BR-04 | Sistem harus mendukung tagihan khusus (nominal di luar tarif klaster) | Tinggi |
| BR-05 | Sistem harus mendukung penerbitan tagihan mundur (periode historis) | Tinggi |
| BR-06 | Tagihan harus melalui flow approval sebelum bisa dibayar | Tinggi |
| BR-07 | Sistem harus memproses pembayaran di loket (single/multi tagihan) | Tinggi |
| BR-08 | Sistem harus menghitung denda 3% jika pembayaran setelah tanggal 20 | Tinggi |
| BR-09 | Nomor kuitansi harus unik dan terformat: GD.YYYY.MM.NNNNNN | Tinggi |
| BR-10 | Sistem harus mendukung cicilan (pembayaran parsial) | Tinggi |
| BR-11 | Sistem harus mendukung pelunasan tagihan mundur | Menengah |
| BR-12 | Pembatalan transaksi harus melalui flow multi-level approval | Tinggi |
| BR-13 | Sistem harus menghasilkan laporan keuangan (bulanan, harian, rekonsiliasi) | Tinggi |
| BR-14 | Sistem harus menghasilkan dokumen PDF (SPT, SPK, rekap) | Tinggi |
| BR-15 | Sistem harus mengelola manajemen user dengan RBAC | Tinggi |
| BR-16 | Sistem harus mencatat audit trail setiap aksi penting | Tinggi |
| BR-17 | Sistem harus mendukung notifikasi WhatsApp otomatis | Menengah |
| BR-18 | Sistem harus mendukung analisis piutang per umur tunggakan | Menengah |

### 2.2 Kebutuhan Teknis

| ID | Kebutuhan | Detail |
|----|-----------|--------|
| TR-01 | PHP >= 8.2 | Laravel 12 requirement |
| TR-02 | MySQL >= 8.0 | InnoDB engine, foreign key support |
| TR-03 | Redis | Cache, queue, rate limiting |
| TR-04 | Composer | Dependency management |
| TR-05 | RESTful API | JSON response, HTTP verbs |
| TR-06 | API Authentication | Laravel Sanctum token-based |
| TR-07 | RBAC | Spatie Laravel Permission |
| TR-08 | Rate Limiting | 60 req/min default, 300 req/min authenticated |
| TR-09 | PDF Generation | barryvdh/laravel-dompdf |
| TR-10 | Queue | Database atau Redis driver |
| TR-11 | File Storage | Local + AWS S3 compatible |
| TR-12 | Logging | Laravel Log + daily rotation |
| TR-13 | API Documentation | L5-Swagger / Scribe |

---

## 3. Arsitektur Aplikasi

### 3.1 Arsitektur High-Level

```
┌─────────────────────────────────────────────────────────────────┐
│                         CLIENT LAYER                            │
│   Web SPA (React/Vue)  │  Mobile App  │  Portal Pelanggan       │
└────────────────────────┬────────────────────────────────────────┘
                         │ HTTPS + Bearer Token
┌────────────────────────▼────────────────────────────────────────┐
│                      API GATEWAY / NGINX                        │
│              SSL Termination, Rate Limiting, Proxy              │
└────────────────────────┬────────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────────┐
│                    LARAVEL 12 APPLICATION                       │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                    MIDDLEWARE PIPELINE                   │   │
│  │  Authenticate → RBAC/Permission → Throttle → Sanitize   │   │
│  └─────────────────────────────────────────────────────────┘   │
│                         │                                       │
│  ┌──────────────────────▼──────────────────────────────────┐   │
│  │                  API ROUTES (v1)                         │   │
│  └──────────────────────┬──────────────────────────────────┘   │
│                         │                                       │
│  ┌──────────────────────▼──────────────────────────────────┐   │
│  │                 CONTROLLER LAYER                         │   │
│  │   Request Validation → Business Logic → Response         │   │
│  └──────┬───────────────────────────────────┬──────────────┘   │
│         │                                   │                   │
│  ┌──────▼──────────┐              ┌──────────▼──────────┐      │
│  │  SERVICE LAYER  │              │   RESOURCE LAYER    │      │
│  │  Business Rules │              │   JSON Transform    │      │
│  └──────┬──────────┘              └─────────────────────┘      │
│         │                                                       │
│  ┌──────▼──────────────────────────────────────────────────┐   │
│  │              REPOSITORY / MODEL LAYER                    │   │
│  │          Eloquent ORM, Query Optimization                │   │
│  └──────┬─────────────────────────┬───────────────────────┘   │
│         │                         │                             │
│  ┌──────▼──────────┐   ┌──────────▼──────────┐                 │
│  │   CACHE LAYER   │   │    QUEUE LAYER       │                 │
│  │     Redis       │   │  Jobs, Notifications │                 │
│  └─────────────────┘   └─────────────────────┘                 │
└──────────────────────────┬──────────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────────┐
│                       DATA LAYER                                │
│   MySQL 8.x (Primary)  │  Redis (Cache)  │  File Storage        │
└─────────────────────────────────────────────────────────────────┘
```

### 3.2 Pola Arsitektur

Sistem menggunakan **Layered Architecture** dengan pola:

- **Controller** — Menerima HTTP request, memanggil Service, mengembalikan JSON Response
- **Form Request** — Validasi input sebelum masuk Controller
- **Service** — Logika bisnis, orchestration antar Repository
- **Repository** — Abstraksi query database, memanggil Eloquent Model
- **Model (Eloquent)** — Representasi tabel, relasi, scope
- **Resource / Transformer** — Format data ke JSON response
- **Policy** — Otorisasi aksi per resource
- **Event / Listener** — Async side effects (notif, audit log)
- **Job** — Background processing (bulk generation, PDF batch)

---

## 4. Struktur Direktori & Standar Pengembangan

### 4.1 Struktur Direktori Laravel 12

```
grandduta-api/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── GenerateBillingMonthly.php      # Artisan command billing bulanan
│   │       ├── SnapshotReceivables.php         # Snapshot piutang akhir bulan
│   │       └── SendBillingNotifications.php    # Kirim notif WA
│   │
│   ├── Exceptions/
│   │   └── Handler.php                         # Global exception handler
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/V1/
│   │   │       ├── AuthController.php
│   │   │       ├── UserController.php
│   │   │       ├── DashboardController.php
│   │   │       ├── ClusterController.php
│   │   │       ├── CustomerController.php
│   │   │       ├── BillingController.php
│   │   │       ├── BillingApprovalController.php
│   │   │       ├── PaymentController.php
│   │   │       ├── InstallmentController.php
│   │   │       ├── BackPaymentController.php
│   │   │       ├── ReversalController.php
│   │   │       ├── ReceivableController.php
│   │   │       ├── ReportController.php
│   │   │       ├── DocumentController.php
│   │   │       ├── LookupController.php
│   │   │       └── NotificationController.php
│   │   │
│   │   ├── Middleware/
│   │   │   ├── ForceJsonResponse.php           # Paksa response JSON
│   │   │   ├── AuditLogger.php                 # Log setiap request
│   │   │   └── CheckPermission.php             # Validasi permission
│   │   │
│   │   └── Requests/
│   │       ├── Auth/
│   │       │   ├── LoginRequest.php
│   │       │   └── ChangePasswordRequest.php
│   │       ├── Customer/
│   │       │   ├── StoreCustomerRequest.php
│   │       │   ├── UpdateCustomerRequest.php
│   │       │   └── ConvertPropertyRequest.php
│   │       ├── Billing/
│   │       │   ├── PrepareBillingRequest.php
│   │       │   ├── PrepareSpecialBillingRequest.php
│   │       │   ├── PrepareBackBillingRequest.php
│   │       │   └── ApproveBillingRequest.php
│   │       ├── Payment/
│   │       │   ├── ProcessPaymentRequest.php
│   │       │   └── StoreInstallmentRequest.php
│   │       ├── Reversal/
│   │       │   ├── SubmitReversalRequest.php
│   │       │   └── ProcessReversalRequest.php
│   │       └── User/
│   │           ├── StoreUserRequest.php
│   │           └── UpdateUserRequest.php
│   │
│   ├── Models/
│   │   ├── User.php
│   │   ├── Cluster.php
│   │   ├── Customer.php
│   │   ├── Billing.php
│   │   ├── Receipt.php
│   │   ├── Installment.php
│   │   ├── Reversal.php
│   │   ├── Receivable.php
│   │   ├── SpecialBillingRate.php
│   │   ├── AuditLog.php
│   │   ├── NotificationQueue.php
│   │   ├── PaymentMethod.php
│   │   ├── PaymentChannel.php
│   │   ├── PropertyType.php
│   │   ├── OccupancyStatus.php
│   │   ├── CustomerStatus.php
│   │   ├── BillingStatus.php
│   │   ├── District.php
│   │   └── Regency.php
│   │
│   ├── Policies/
│   │   ├── CustomerPolicy.php
│   │   ├── BillingPolicy.php
│   │   ├── PaymentPolicy.php
│   │   ├── ReversalPolicy.php
│   │   └── UserPolicy.php
│   │
│   ├── Repositories/
│   │   ├── Contracts/
│   │   │   ├── CustomerRepositoryInterface.php
│   │   │   ├── BillingRepositoryInterface.php
│   │   │   └── PaymentRepositoryInterface.php
│   │   ├── CustomerRepository.php
│   │   ├── BillingRepository.php
│   │   ├── PaymentRepository.php
│   │   ├── ReceiptRepository.php
│   │   ├── InstallmentRepository.php
│   │   └── ReportRepository.php
│   │
│   ├── Services/
│   │   ├── AuthService.php
│   │   ├── BillingService.php              # Engine penagihan
│   │   ├── PaymentService.php              # Proses pembayaran
│   │   ├── ReversalService.php             # Pembatalan transaksi
│   │   ├── ReceiptService.php              # Generate nomor kuitansi
│   │   ├── PenaltyService.php              # Hitung denda
│   │   ├── ReceivableService.php           # Manajemen piutang
│   │   ├── ReportService.php               # Laporan keuangan
│   │   ├── DocumentService.php             # Generate PDF
│   │   ├── NotificationService.php         # Notifikasi WA/SMS
│   │   └── AuditService.php               # Audit logging
│   │
│   ├── Events/
│   │   ├── PaymentProcessed.php
│   │   ├── BillingApproved.php
│   │   ├── ReversalApproved.php
│   │   └── CustomerUpdated.php
│   │
│   ├── Listeners/
│   │   ├── SendPaymentNotification.php
│   │   ├── RecordAuditLog.php
│   │   ├── UpdateReceivableOnPayment.php
│   │   └── SendReversalStatusNotification.php
│   │
│   ├── Jobs/
│   │   ├── GenerateMonthlyBillingJob.php
│   │   ├── GeneratePdfDocumentJob.php
│   │   ├── SendWhatsAppNotificationJob.php
│   │   └── SnapshotReceivablesJob.php
│   │
│   └── Resources/
│       ├── UserResource.php
│       ├── ClusterResource.php
│       ├── CustomerResource.php
│       ├── BillingResource.php
│       ├── ReceiptResource.php
│       ├── InstallmentResource.php
│       ├── ReversalResource.php
│       └── Collections/
│           ├── CustomerCollection.php
│           └── BillingCollection.php
│
├── bootstrap/
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── cache.php
│   ├── database.php
│   ├── queue.php
│   ├── sanctum.php
│   └── grandduta.php                       # Config kustom aplikasi
│
├── database/
│   ├── migrations/                         # 20+ migration files
│   ├── seeders/
│   │   ├── DatabaseSeeder.php
│   │   ├── UserSeeder.php
│   │   ├── ClusterSeeder.php
│   │   ├── LookupSeeder.php
│   │   └── RolePermissionSeeder.php
│   └── factories/
│       ├── CustomerFactory.php
│       ├── BillingFactory.php
│       └── UserFactory.php
│
├── routes/
│   └── api.php                             # Semua route API
│
├── storage/
│   ├── app/
│   │   ├── public/
│   │   └── documents/                      # Generated PDFs
│   └── logs/
│
└── tests/
    ├── Feature/
    │   ├── Auth/
    │   ├── Customer/
    │   ├── Billing/
    │   ├── Payment/
    │   └── Report/
    └── Unit/
        ├── Services/
        └── Models/
```

### 4.2 Standar Pengembangan

#### Konvensi Penamaan

| Komponen | Konvensi | Contoh |
|----------|----------|--------|
| Controller | PascalCase + Controller | `BillingController` |
| Model | PascalCase singular | `Billing`, `Customer` |
| Migration | snake_case + timestamp | `2026_01_01_create_billings_table` |
| Request | PascalCase + Request | `ProcessPaymentRequest` |
| Service | PascalCase + Service | `BillingService` |
| Repository | PascalCase + Repository | `BillingRepository` |
| Resource | PascalCase + Resource | `BillingResource` |
| Job | PascalCase + Job | `GenerateMonthlyBillingJob` |
| Event | PascalCase (aksi past) | `PaymentProcessed` |
| Listener | PascalCase + aksi | `SendPaymentNotification` |
| Route name | snake_case dengan dot | `billings.prepare`, `payments.process` |
| DB kolom | snake_case | `customer_code`, `billing_amount` |
| DB tabel | snake_case plural | `customers`, `billings`, `receipts` |

#### Standar Coding

- **PSR-12** untuk code style
- **Strict types**: `declare(strict_types=1)` di setiap file
- **Type hints**: wajib di semua method (parameter dan return type)
- **Docblock**: hanya untuk method kompleks, bukan trivial
- **Constants**: gunakan `const` di class atau `config()` untuk nilai global
- **No business logic di Controller**: delegasikan ke Service
- **No direct DB query di Controller**: melalui Repository

#### Standar Database

- Semua tabel menggunakan **InnoDB** engine
- Semua tabel menggunakan **utf8mb4_unicode_ci** collation
- Semua FK harus didefinisikan dengan `FOREIGN KEY CONSTRAINT`
- Semua tabel operasional wajib memiliki `created_at`, `updated_at`
- Tabel yang mendukung soft delete wajib memiliki `deleted_at`
- Primary key: gunakan `BIGINT UNSIGNED AUTO_INCREMENT` kecuali ada alasan khusus

---

## 5. Desain Database

### 5.1 ERD (Entity Relationship Diagram)

```
users ──────────────── roles (via model_has_roles)
  │                      │
  │                    permissions (via role_has_permissions)
  │
  ├── customers (created_by, updated_by)
  │     │
  │     ├── cluster_id ──────► clusters
  │     ├── property_type_id ► property_types (B/K/P)
  │     ├── occupancy_id ─────► occupancy_statuses
  │     ├── status_id ────────► customer_statuses (AK/RK/TA)
  │     ├── district_id ──────► districts
  │     │     └── regency_id ─► regencies
  │     │
  │     ├── billings (1:M)
  │     │     │
  │     │     ├── billing_status_id ► billing_statuses (01/02)
  │     │     ├── approved_by ───────► users
  │     │     ├── processed_by ──────► users
  │     │     └── receipt_number ────► receipts (1:1 nullable)
  │     │             │
  │     │             ├── payment_method_id ► payment_methods
  │     │             └── payment_channel_id► payment_channels
  │     │
  │     ├── installments (1:M)
  │     │
  │     └── receivables (1:M)
  │
  ├── reversals
  │     ├── receipt_number ────► receipts
  │     ├── submitted_by ──────► users
  │     └── approved_by ───────► users
  │
  ├── special_billing_rates
  │     └── customer_id ───────► customers
  │
  └── audit_logs
        └── user_id ───────────► users
```

### 5.2 Migrasi Database (Semua Tabel)

#### Tabel: `users`

```sql
CREATE TABLE users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    username        VARCHAR(50) NOT NULL UNIQUE,
    email           VARCHAR(100) UNIQUE,
    password        VARCHAR(255) NOT NULL,          -- bcrypt
    is_active       BOOLEAN DEFAULT TRUE,
    last_login_at   TIMESTAMP NULL,
    last_login_ip   VARCHAR(45) NULL,
    remember_token  VARCHAR(100) NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    deleted_at      TIMESTAMP NULL,                 -- soft delete
    INDEX idx_username (username),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Tabel: `clusters`

```sql
CREATE TABLE clusters (
    id              VARCHAR(2) PRIMARY KEY,         -- DO, GA, JA, dll.
    name            VARCHAR(50) NOT NULL,
    monthly_rate    DECIMAL(15,2) NOT NULL DEFAULT 0,
    description     TEXT NULL,
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Tabel: `property_types`

```sql
CREATE TABLE property_types (
    id              CHAR(1) PRIMARY KEY,            -- B, K, P
    name            VARCHAR(30) NOT NULL,
    description     VARCHAR(100) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed data:
-- B = Bangunan (Building)
-- K = Kavling Developer (Developer Lot)
-- P = Kavling Pelanggan (Customer Lot)
```

#### Tabel: `occupancy_statuses`

```sql
CREATE TABLE occupancy_statuses (
    id              CHAR(1) PRIMARY KEY,            -- 1, 2
    name            VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1 = Dihuni (Occupied)
-- 2 = Kosong (Vacant)
```

#### Tabel: `customer_statuses`

```sql
CREATE TABLE customer_statuses (
    id              VARCHAR(2) PRIMARY KEY,         -- AK, RK, TA
    name            VARCHAR(30) NOT NULL,
    description     VARCHAR(100) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AK = Aktif
-- RK = Rumah Kosong
-- TA = Tidak Aktif
```

#### Tabel: `billing_statuses`

```sql
CREATE TABLE billing_statuses (
    id              VARCHAR(2) PRIMARY KEY,         -- 01, 02
    name            VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 01 = Belum Bayar (Unpaid)
-- 02 = Lunas (Paid)
```

#### Tabel: `payment_methods`

```sql
CREATE TABLE payment_methods (
    id              CHAR(1) PRIMARY KEY,            -- C, D
    name            VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- C = Cash / Tunai
-- D = Debit / Transfer
```

#### Tabel: `payment_channels`

```sql
CREATE TABLE payment_channels (
    id              CHAR(1) PRIMARY KEY,
    name            VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Tabel: `regencies`

```sql
CREATE TABLE regencies (
    id              VARCHAR(5) PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Tabel: `districts`

```sql
CREATE TABLE districts (
    id              VARCHAR(5) PRIMARY KEY,
    regency_id      VARCHAR(5) NOT NULL,
    name            VARCHAR(100) NOT NULL,
    FOREIGN KEY (regency_id) REFERENCES regencies(id),
    INDEX idx_regency (regency_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Tabel: `customers` ⭐ Tabel Utama

```sql
CREATE TABLE customers (
    id                  VARCHAR(5) PRIMARY KEY,    -- DO001, GA012 (5 char)
    name                VARCHAR(100) NOT NULL,
    cluster_id          VARCHAR(2) NOT NULL,
    block               VARCHAR(5) NOT NULL,
    lot_number          VARCHAR(10) NOT NULL,
    property_type_id    CHAR(1) NOT NULL DEFAULT 'B',
    phone               VARCHAR(20) NULL,
    telephone           VARCHAR(20) NULL,
    id_card_address     VARCHAR(200) NULL,
    district_id         VARCHAR(5) NULL,
    building_area       DECIMAL(8,2) NULL,         -- Luas Bangunan (m²)
    land_area           DECIMAL(8,2) NULL,          -- Luas Tanah (m²)
    email               VARCHAR(100) NULL,
    handover_date       DATE NULL,                  -- Tgl serah terima
    occupancy_id        CHAR(1) DEFAULT '1',
    status_id           VARCHAR(2) DEFAULT 'AK',
    is_penalty_eligible BOOLEAN DEFAULT TRUE,       -- kenadenda
    is_discount_eligible BOOLEAN DEFAULT FALSE,     -- kenadiskon
    notes               TEXT NULL,
    created_by          BIGINT UNSIGNED NULL,
    updated_by          BIGINT UNSIGNED NULL,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,
    deleted_at          TIMESTAMP NULL,

    FOREIGN KEY (cluster_id) REFERENCES clusters(id),
    FOREIGN KEY (property_type_id) REFERENCES property_types(id),
    FOREIGN KEY (occupancy_id) REFERENCES occupancy_statuses(id),
    FOREIGN KEY (status_id) REFERENCES customer_statuses(id),
    FOREIGN KEY (district_id) REFERENCES districts(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id),

    INDEX idx_cluster (cluster_id),
    INDEX idx_status (status_id),
    INDEX idx_block_lot (block, lot_number),
    INDEX idx_name (name),
    UNIQUE KEY uk_block_lot_cluster (cluster_id, block, lot_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Tabel: `special_billing_rates`

```sql
CREATE TABLE special_billing_rates (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id     VARCHAR(5) NOT NULL,
    amount          DECIMAL(15,2) NOT NULL,
    notes           VARCHAR(200) NULL,
    is_active       BOOLEAN DEFAULT TRUE,
    created_by      BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Tabel: `billings` ⭐ Tabel Utama

```sql
CREATE TABLE billings (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id         VARCHAR(5) NOT NULL,
    year                SMALLINT UNSIGNED NOT NULL,     -- 2024, 2025
    month               TINYINT UNSIGNED NOT NULL,      -- 1-12
    amount              DECIMAL(15,2) NOT NULL,          -- Tagihan pokok
    penalty             DECIMAL(15,2) DEFAULT 0,         -- Denda (diisi saat bayar)
    discount            DECIMAL(15,2) DEFAULT 0,
    status_id           VARCHAR(2) DEFAULT '01',
    is_penalty_eligible BOOLEAN DEFAULT TRUE,
    is_discount_eligible BOOLEAN DEFAULT FALSE,
    billing_type        ENUM('regular','special','back') DEFAULT 'regular',
    approved_by         BIGINT UNSIGNED NULL,
    approved_at         TIMESTAMP NULL,
    approval_notes      VARCHAR(200) NULL,
    paid_at             TIMESTAMP NULL,
    receipt_number      VARCHAR(16) NULL,
    loket_code          VARCHAR(10) NULL,
    processed_by        BIGINT UNSIGNED NULL,
    spt_print_count     SMALLINT DEFAULT 0,
    created_by          BIGINT UNSIGNED NULL,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,

    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (status_id) REFERENCES billing_statuses(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (processed_by) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),

    UNIQUE KEY uk_customer_period (customer_id, year, month),
    INDEX idx_status (status_id),
    INDEX idx_period (year, month),
    INDEX idx_customer_status (customer_id, status_id),
    INDEX idx_receipt (receipt_number),
    INDEX idx_paid_at (paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Tabel: `receipts` ⭐ Tabel Utama

```sql
CREATE TABLE receipts (
    number              VARCHAR(16) PRIMARY KEY,    -- GD.2025.06.000001
    customer_id         VARCHAR(5) NOT NULL,
    transaction_date    DATETIME NOT NULL,
    customer_name       VARCHAR(100) NOT NULL,      -- Snapshot saat transaksi
    cluster_name        VARCHAR(50) NOT NULL,       -- Snapshot
    block               VARCHAR(5) NOT NULL,        -- Snapshot
    lot_number          VARCHAR(10) NOT NULL,       -- Snapshot
    total_billing       DECIMAL(15,2) NOT NULL,     -- Total pokok
    total_penalty       DECIMAL(15,2) DEFAULT 0,
    grand_total         DECIMAL(15,2) NOT NULL,
    billing_count       TINYINT UNSIGNED DEFAULT 1, -- Jumlah tagihan dibayar
    billing_periods     VARCHAR(200) NULL,           -- "Jan 2025, Feb 2025"
    loket_code          VARCHAR(20) NULL,
    cashier_name        VARCHAR(50) NULL,
    payment_method_id   CHAR(1) NOT NULL DEFAULT 'C',
    payment_channel_id  CHAR(1) NULL,
    notes               TEXT NULL,
    created_by          BIGINT UNSIGNED NULL,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,

    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id),
    FOREIGN KEY (payment_channel_id) REFERENCES payment_channels(id),
    FOREIGN KEY (created_by) REFERENCES users(id),

    INDEX idx_customer (customer_id),
    INDEX idx_date (transaction_date),
    INDEX idx_cashier (cashier_name),
    INDEX idx_year_month (
        YEAR(transaction_date),
        MONTH(transaction_date)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Tabel: `installments`

```sql
CREATE TABLE installments (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id     VARCHAR(5) NOT NULL,
    amount          DECIMAL(15,2) NOT NULL,
    payment_date    DATE NOT NULL,
    notes           VARCHAR(200) NULL,
    allocated_to    VARCHAR(200) NULL,              -- Tagihan yang dialokasikan
    created_by      BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_customer (customer_id),
    INDEX idx_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Tabel: `reversals`

```sql
CREATE TABLE reversals (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_number  VARCHAR(16) NOT NULL,
    reason          TEXT NOT NULL,
    status          ENUM('pending','approved','rejected') DEFAULT 'pending',
    submitted_by    BIGINT UNSIGNED NOT NULL,
    submitted_at    TIMESTAMP NOT NULL,
    reviewed_by     BIGINT UNSIGNED NULL,
    reviewed_at     TIMESTAMP NULL,
    review_notes    VARCHAR(200) NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (receipt_number) REFERENCES receipts(number),
    FOREIGN KEY (submitted_by) REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_receipt (receipt_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Tabel: `receivables`

```sql
CREATE TABLE receivables (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id     VARCHAR(5) NOT NULL,
    billing_id      BIGINT UNSIGNED NOT NULL,
    year            SMALLINT UNSIGNED NOT NULL,
    month           TINYINT UNSIGNED NOT NULL,
    amount          DECIMAL(15,2) NOT NULL,
    snapshot_date   DATE NOT NULL,                  -- Tanggal snapshot
    is_settled      BOOLEAN DEFAULT FALSE,
    settled_at      TIMESTAMP NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (billing_id) REFERENCES billings(id),
    INDEX idx_customer (customer_id),
    INDEX idx_period (year, month),
    INDEX idx_settled (is_settled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Tabel: `audit_logs`

```sql
CREATE TABLE audit_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NULL,
    action          VARCHAR(50) NOT NULL,           -- CREATE, UPDATE, DELETE, LOGIN
    module          VARCHAR(50) NOT NULL,           -- customers, billings, payments
    record_id       VARCHAR(50) NULL,
    old_data        JSON NULL,
    new_data        JSON NULL,
    ip_address      VARCHAR(45) NULL,
    user_agent      VARCHAR(255) NULL,
    created_at      TIMESTAMP NULL,

    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user (user_id),
    INDEX idx_module (module),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Tabel: `notification_queues`

```sql
CREATE TABLE notification_queues (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id     VARCHAR(5) NOT NULL,
    type            VARCHAR(30) NOT NULL,           -- billing_created, payment_confirmed
    channel         VARCHAR(20) DEFAULT 'whatsapp', -- whatsapp, sms, email
    recipient       VARCHAR(100) NOT NULL,           -- phone/email
    message         TEXT NOT NULL,
    status          ENUM('pending','sent','failed') DEFAULT 'pending',
    attempts        TINYINT DEFAULT 0,
    sent_at         TIMESTAMP NULL,
    failed_reason   VARCHAR(255) NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (customer_id) REFERENCES customers(id),
    INDEX idx_status (status),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5.3 Database Stored Function

```sql
-- Menggantikan f_denda() dari sistem lama
-- Dipanggil dari PHP/Eloquent di PenaltyService, bukan langsung di query
-- Logika denda dipindahkan ke Laravel Service agar lebih testable

-- Tetap tersedia di DB sebagai reference:
DELIMITER $$
CREATE FUNCTION calculate_penalty(
    p_amount        DECIMAL(15,2),
    p_eligible      BOOLEAN
) RETURNS DECIMAL(15,2) DETERMINISTIC
BEGIN
    IF DAY(NOW()) > 20 AND p_eligible = TRUE THEN
        RETURN p_amount * 0.03;
    ELSE
        RETURN 0;
    END IF;
END$$
DELIMITER ;
```

### 5.4 Database Views

```sql
-- View: pelanggan aktif dengan detail klaster
CREATE VIEW v_active_customers AS
    SELECT
        c.id,
        c.name,
        c.cluster_id,
        cl.name AS cluster_name,
        cl.monthly_rate,
        c.block,
        c.lot_number,
        c.property_type_id,
        c.status_id,
        c.is_penalty_eligible
    FROM customers c
    JOIN clusters cl ON c.cluster_id = cl.id
    WHERE c.status_id = 'AK'
      AND c.deleted_at IS NULL;

-- View: tagihan belum lunas
CREATE VIEW v_unpaid_billings AS
    SELECT
        b.*,
        c.name AS customer_name,
        cl.name AS cluster_name,
        c.block,
        c.lot_number
    FROM billings b
    JOIN customers c ON b.customer_id = c.id
    JOIN clusters cl ON c.cluster_id = cl.id
    WHERE b.status_id = '01'
      AND b.approved_by IS NOT NULL;
```

### 5.5 Indeks & Performa

```sql
-- Composite index untuk query loket (pencarian tagihan belum bayar)
ALTER TABLE billings ADD INDEX idx_loket_search
    (customer_id, status_id, year, month);

-- Composite index untuk laporan bulanan
ALTER TABLE billings ADD INDEX idx_report_monthly
    (year, month, status_id);

-- Index untuk pencarian pelanggan
ALTER TABLE customers ADD FULLTEXT INDEX ft_customer_search (name);
```

---

## 6. Komponen Aplikasi Laravel

### 6.1 Models (Eloquent)

#### `Customer` Model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use SoftDeletes;

    protected $table = 'customers';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'cluster_id', 'block', 'lot_number',
        'property_type_id', 'phone', 'telephone', 'id_card_address',
        'district_id', 'building_area', 'land_area', 'email',
        'handover_date', 'occupancy_id', 'status_id',
        'is_penalty_eligible', 'is_discount_eligible', 'notes',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'building_area'         => 'decimal:2',
        'land_area'             => 'decimal:2',
        'handover_date'         => 'date',
        'is_penalty_eligible'   => 'boolean',
        'is_discount_eligible'  => 'boolean',
    ];

    // Relasi
    public function cluster(): BelongsTo
    {
        return $this->belongsTo(Cluster::class, 'cluster_id');
    }

    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class, 'property_type_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(CustomerStatus::class, 'status_id');
    }

    public function billings(): HasMany
    {
        return $this->hasMany(Billing::class, 'customer_id');
    }

    public function unpaidBillings(): HasMany
    {
        return $this->hasMany(Billing::class, 'customer_id')
            ->where('status_id', '01')
            ->whereNotNull('approved_by')
            ->orderBy('year')
            ->orderBy('month');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class, 'customer_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class, 'customer_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status_id', 'AK');
    }

    public function scopeByCluster($query, string $clusterId)
    {
        return $query->where('cluster_id', $clusterId);
    }

    // Accessors
    public function getFullAddressAttribute(): string
    {
        return "Blok {$this->block}/{$this->lot_number}, Klaster {$this->cluster->name}";
    }

    public function isActive(): bool
    {
        return $this->status_id === 'AK';
    }
}
```

#### `Billing` Model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Billing extends Model
{
    protected $table = 'billings';

    protected $fillable = [
        'customer_id', 'year', 'month', 'amount', 'penalty', 'discount',
        'status_id', 'is_penalty_eligible', 'is_discount_eligible',
        'billing_type', 'approved_by', 'approved_at', 'approval_notes',
        'paid_at', 'receipt_number', 'loket_code', 'processed_by',
        'spt_print_count', 'created_by',
    ];

    protected $casts = [
        'amount'                => 'decimal:2',
        'penalty'               => 'decimal:2',
        'discount'              => 'decimal:2',
        'approved_at'           => 'datetime',
        'paid_at'               => 'datetime',
        'is_penalty_eligible'   => 'boolean',
        'is_discount_eligible'  => 'boolean',
        'year'                  => 'integer',
        'month'                 => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(BillingStatus::class, 'status_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status_id', '01');
    }

    public function scopePaid($query)
    {
        return $query->where('status_id', '02');
    }

    public function scopeApproved($query)
    {
        return $query->whereNotNull('approved_by');
    }

    public function scopePeriod($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }

    public function isPaid(): bool
    {
        return $this->status_id === '02';
    }

    public function isApproved(): bool
    {
        return $this->approved_by !== null;
    }

    public function getPeriodLabelAttribute(): string
    {
        $months = [
            1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April',
            5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus',
            9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember',
        ];
        return ($months[$this->month] ?? '') . ' ' . $this->year;
    }
}
```

### 6.2 Services

#### `PenaltyService`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;

class PenaltyService
{
    private const PENALTY_RATE = 0.03;      // 3%
    private const PENALTY_DAY_THRESHOLD = 20;

    public function calculate(float $amount, bool $isEligible): float
    {
        if (!$isEligible) {
            return 0.0;
        }

        if (Carbon::now()->day > self::PENALTY_DAY_THRESHOLD) {
            return round($amount * self::PENALTY_RATE, 2);
        }

        return 0.0;
    }

    public function calculateBatch(array $billings): array
    {
        return array_map(function ($billing) {
            return [
                ...$billing,
                'penalty' => $this->calculate(
                    (float) $billing['amount'],
                    (bool) $billing['is_penalty_eligible']
                ),
            ];
        }, $billings);
    }

    public function isPenaltyApplicable(): bool
    {
        return Carbon::now()->day > self::PENALTY_DAY_THRESHOLD;
    }
}
```

#### `ReceiptService`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Receipt;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReceiptService
{
    public function generateReceiptNumber(): string
    {
        return DB::transaction(function () {
            $now = Carbon::now();
            $year = $now->year;
            $month = $now->month;

            // Lock tabel untuk prevent race condition
            $lastReceipt = Receipt::whereYear('transaction_date', $year)
                ->whereMonth('transaction_date', $month)
                ->lockForUpdate()
                ->orderByDesc('number')
                ->first();

            $counter = 1;
            if ($lastReceipt) {
                // Extract counter dari format GD.YYYY.MM.NNNNNN
                $parts = explode('.', $lastReceipt->number);
                $counter = (int) end($parts) + 1;
            }

            return sprintf(
                'GD.%04d.%02d.%06d',
                $year,
                $month,
                $counter
            );
        });
    }
}
```

#### `BillingService`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Billing;
use App\Models\Customer;
use App\Models\SpecialBillingRate;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function __construct(
        private readonly PenaltyService $penaltyService,
    ) {}

    /**
     * Generate tagihan bulanan untuk semua pelanggan aktif.
     * Melewati pelanggan yang sudah punya tagihan untuk periode tersebut.
     */
    public function prepareMonthlyBilling(int $year, int $month, int $createdBy): array
    {
        $customers = Customer::active()
            ->with('cluster')
            ->get();

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($customers, $year, $month, $createdBy, &$created, &$skipped) {
            foreach ($customers as $customer) {
                $exists = Billing::where('customer_id', $customer->id)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                // Cek apakah ada tarif khusus
                $specialRate = SpecialBillingRate::where('customer_id', $customer->id)
                    ->where('is_active', true)
                    ->first();

                $amount = $specialRate
                    ? $specialRate->amount
                    : $customer->cluster->monthly_rate;

                Billing::create([
                    'customer_id'           => $customer->id,
                    'year'                  => $year,
                    'month'                 => $month,
                    'amount'                => $amount,
                    'status_id'             => '01',
                    'is_penalty_eligible'   => $customer->is_penalty_eligible,
                    'is_discount_eligible'  => $customer->is_discount_eligible,
                    'billing_type'          => $specialRate ? 'special' : 'regular',
                    'created_by'            => $createdBy,
                ]);

                $created++;
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Generate tagihan untuk periode mundur (historis).
     */
    public function prepareBackBilling(
        string $customerId,
        int $year,
        int $month,
        float $amount,
        int $createdBy
    ): Billing {
        $exists = Billing::where('customer_id', $customerId)
            ->where('year', $year)
            ->where('month', $month)
            ->exists();

        if ($exists) {
            throw new \Exception("Tagihan periode {$month}/{$year} untuk pelanggan {$customerId} sudah ada.");
        }

        return Billing::create([
            'customer_id'   => $customerId,
            'year'          => $year,
            'month'         => $month,
            'amount'        => $amount,
            'status_id'     => '01',
            'billing_type'  => 'back',
            'created_by'    => $createdBy,
        ]);
    }
}
```

### 6.3 Repositories

#### `BillingRepository`

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Billing;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class BillingRepository
{
    public function getUnpaidByCustomer(string $customerId): Collection
    {
        return Billing::where('customer_id', $customerId)
            ->unpaid()
            ->approved()
            ->with('customer.cluster')
            ->orderBy('year')
            ->orderBy('month')
            ->get();
    }

    public function getByPeriod(int $year, int $month): LengthAwarePaginator
    {
        return Billing::period($year, $month)
            ->with(['customer.cluster', 'status'])
            ->paginate(50);
    }

    public function getPendingApproval(): Collection
    {
        return Billing::unpaid()
            ->whereNull('approved_by')
            ->with('customer.cluster')
            ->orderBy('created_at')
            ->get();
    }

    public function getMonthlyReport(int $year, int $month): array
    {
        return Billing::period($year, $month)
            ->join('customers', 'billings.customer_id', '=', 'customers.id')
            ->join('clusters', 'customers.cluster_id', '=', 'clusters.id')
            ->selectRaw('
                clusters.id as cluster_id,
                clusters.name as cluster_name,
                COUNT(billings.id) as total_count,
                SUM(billings.amount) as total_amount,
                SUM(billings.penalty) as total_penalty,
                SUM(CASE WHEN billings.status_id = \'02\' THEN billings.amount + billings.penalty ELSE 0 END) as total_paid,
                SUM(CASE WHEN billings.status_id = \'01\' THEN billings.amount ELSE 0 END) as total_unpaid,
                SUM(CASE WHEN billings.status_id = \'02\' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN billings.status_id = \'01\' THEN 1 ELSE 0 END) as unpaid_count
            ')
            ->groupBy('clusters.id', 'clusters.name')
            ->orderBy('clusters.id')
            ->get()
            ->toArray();
    }
}
```

### 6.4 Middleware

#### `ForceJsonResponse`

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): mixed
    {
        $request->headers->set('Accept', 'application/json');
        return $next($request);
    }
}
```

#### `AuditLogger`

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(private readonly AuditService $auditService) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        // Log hanya request yang mutasi data (bukan GET)
        if (!in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            $this->auditService->logRequest($request, $response);
        }

        return $response;
    }
}
```

### 6.5 Policies

#### `BillingPolicy`

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Billing;
use App\Models\User;

class BillingPolicy
{
    public function prepare(User $user): bool
    {
        return $user->hasAnyRole(['root', 'back_office']);
    }

    public function approve(User $user): bool
    {
        return $user->hasAnyRole(['root', 'back_office']);
    }

    public function viewAny(User $user): bool
    {
        return true; // Semua role bisa lihat
    }

    public function update(User $user, Billing $billing): bool
    {
        return $user->hasAnyRole(['root', 'back_office'])
            && !$billing->isPaid();
    }
}
```

### 6.6 Events & Listeners

```php
// Event: PaymentProcessed
class PaymentProcessed
{
    public function __construct(
        public readonly Receipt $receipt,
        public readonly array $billingIds,
    ) {}
}

// Listener: SendPaymentNotification
class SendPaymentNotification
{
    public function handle(PaymentProcessed $event): void
    {
        SendWhatsAppNotificationJob::dispatch(
            $event->receipt->customer_id,
            'payment_confirmed',
            [
                'receipt_number' => $event->receipt->number,
                'grand_total'    => $event->receipt->grand_total,
                'period'         => $event->receipt->billing_periods,
            ]
        )->delay(now()->addSeconds(5));
    }
}
```

### 6.7 Jobs

```php
// GenerateMonthlyBillingJob
class GenerateMonthlyBillingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $year,
        private readonly int $month,
        private readonly int $createdBy,
    ) {}

    public function handle(BillingService $billingService): void
    {
        $result = $billingService->prepareMonthlyBilling(
            $this->year,
            $this->month,
            $this->createdBy
        );

        Log::info('Monthly billing generated', $result);
    }
}
```

### 6.8 Artisan Commands

```php
// Perintah generate tagihan bulanan via CLI/Scheduler
class GenerateBillingMonthly extends Command
{
    protected $signature = 'grandduta:billing:generate
                            {year : Tahun (format: 2025)}
                            {month : Bulan (1-12)}
                            {--user-id=1 : ID user yang menjalankan}';

    protected $description = 'Generate tagihan bulanan untuk semua pelanggan aktif';

    public function handle(BillingService $billingService): int
    {
        $result = $billingService->prepareMonthlyBilling(
            (int) $this->argument('year'),
            (int) $this->argument('month'),
            (int) $this->option('user-id')
        );

        $this->info("Berhasil: {$result['created']} tagihan dibuat.");
        $this->info("Dilewati: {$result['skipped']} sudah ada.");

        return Command::SUCCESS;
    }
}
```

---

*Lanjutan di TECHNICAL_SPEC_PART2.md*
