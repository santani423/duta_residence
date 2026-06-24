# GRANDDUTA — Technical Spec Part 2
## Modul, Business Flow, Auth, RBAC, API Standards

---

## 7. Modul & Fitur Aplikasi

### 7.1 Modul Auth

**Scope:** Login, logout, refresh token, ganti password  
**Controller:** `AuthController`  
**Service:** `AuthService`  

| Fitur | Deskripsi |
|-------|-----------|
| Login | Autentikasi dengan username+password, return Bearer token |
| Logout | Revoke token aktif |
| Me | Profil user yang sedang login |
| Change Password | Ganti password dengan verifikasi password lama |

### 7.2 Modul Master Data

#### 7.2.1 Cluster (Klaster)

**Controller:** `ClusterController`  
**Model:** `Cluster`  

| Fitur | Role | Deskripsi |
|-------|------|-----------|
| List klaster | Semua | Daftar 14 klaster + tarif |
| Detail klaster | Semua | Info klaster beserta tarif |
| Update tarif | root, back_office | Update monthly_rate (berlaku bulan berikutnya) |

#### 7.2.2 Customer (Pelanggan)

**Controller:** `CustomerController`  
**Service:** `CustomerService`  

| Fitur | Role | Deskripsi |
|-------|------|-----------|
| List pelanggan | root, back_office, loket, cs | Dengan filter: klaster, blok, status, tipe properti |
| Detail pelanggan | Semua | Info lengkap + tagihan aktif |
| Tambah pelanggan | root, back_office | Insert data baru |
| Edit pelanggan | root, back_office | Update data |
| Hapus pelanggan | root | Soft delete |
| Konversi properti | root, back_office | Ubah tipe K → B |
| Cari pelanggan | Semua | Flexible search: nama/blok/kavling |

### 7.3 Modul Penagihan

#### 7.3.1 Billing Preparation (Penyiapan Tagihan)

**Controller:** `BillingController`  
**Service:** `BillingService`  

| Fitur | Role | Deskripsi |
|-------|------|-----------|
| Generate tagihan bulanan | root, back_office | Buat tagihan massal semua pelanggan aktif |
| Generate tagihan khusus | root, back_office | Tagihan per pelanggan dengan nominal khusus |
| Generate tagihan mundur | root, back_office | Tagihan periode historis |
| List tagihan | Semua | Filter: periode, status, klaster |
| Detail tagihan | Semua | Info tagihan + riwayat |
| Update tagihan | root, back_office | Update nominal (sebelum approve) |
| Hapus tagihan | root | Soft delete (sebelum approve) |

#### 7.3.2 Billing Approval

**Controller:** `BillingApprovalController`  

| Fitur | Role | Deskripsi |
|-------|------|-----------|
| List pending approval | root, back_office | Tagihan belum di-approve |
| Approve tagihan | root, back_office | Approve single/batch |
| Batalkan approval | root | Revoke approval yang sudah diberikan |

### 7.4 Modul Pembayaran

#### 7.4.1 Payment (Loket)

**Controller:** `PaymentController`  
**Service:** `PaymentService`  

| Fitur | Role | Deskripsi |
|-------|------|-----------|
| Cari tagihan belum lunas | root, back_office, loket | Cari per ID pelanggan |
| Preview total + denda | root, back_office, loket | Kalkulasi denda real-time |
| Proses pembayaran | root, back_office, loket | Bayar 1/lebih tagihan sekaligus |
| Detail kuitansi | root, back_office, loket | Info kuitansi |
| List kuitansi harian | root, back_office | LPP per tanggal |

#### 7.4.2 Installment (Cicilan)

**Controller:** `InstallmentController`  

| Fitur | Role | Deskripsi |
|-------|------|-----------|
| Input cicilan | root, back_office, loket | Catat pembayaran cicilan |
| List cicilan pelanggan | root, back_office, loket | Riwayat cicilan |
| Alokasi cicilan | root, back_office | Alokasikan cicilan ke tagihan |

#### 7.4.3 Back Payment (Pelunasan Mundur)

**Controller:** `BackPaymentController`  

| Fitur | Role | Deskripsi |
|-------|------|-----------|
| Proses pelunasan mundur | root, back_office | Bayar tagihan periode lampau |

### 7.5 Modul Pembatalan (Reversal)

**Controller:** `ReversalController`  
**Service:** `ReversalService`  

| Fitur | Role | Deskripsi |
|-------|------|-----------|
| Ajukan pembatalan | root, back_office, loket | Submit reversal request |
| List pending reversal | root, back_office | Daftar pembatalan menunggu |
| Approve reversal | root, back_office | Setujui → rollback transaksi |
| Reject reversal | root, back_office | Tolak pembatalan |
| List riwayat reversal | root, back_office | Laporan pembatalan |

### 7.6 Modul Laporan

**Controller:** `ReportController`  
**Service:** `ReportService`  

| Laporan | Endpoint | Role | Deskripsi |
|---------|----------|------|-----------|
| Dashboard summary | `GET /reports/dashboard` | Semua | Widget KPI dashboard |
| Laporan bulanan | `GET /reports/monthly` | root, back_office | Per klaster, per bulan |
| LPP (harian) | `GET /reports/daily-receipt` | root, back_office | Penerimaan per tanggal |
| RPP (rekonsiliasi) | `GET /reports/reconciliation` | root, back_office | Tagihan vs. penerimaan |
| Laporan kolektor | `GET /reports/collector` | root, back_office | Performa per kasir |
| Analisis piutang | `GET /reports/receivables` | root, back_office | Umur piutang |

### 7.7 Modul Dokumen PDF

**Controller:** `DocumentController`  
**Service:** `DocumentService`  

| Dokumen | Endpoint | Role | Deskripsi |
|---------|----------|------|-----------|
| Cetak SPT | `GET /documents/spt/{receipt}` | root, back_office, loket | Surat Pembayaran Tunai |
| Cetak SPK | `GET /documents/spk/{billing}` | root, back_office, loket | Surat Pemberitahuan Kredit |
| Rekap tagihan | `GET /documents/billing-recap` | root, back_office | Rekap per periode/klaster |
| Master pelanggan | `GET /documents/customer-list` | root, back_office | Daftar pelanggan PDF |
| Rekap klaster | `GET /documents/cluster-recap` | root, back_office | Rekap per klaster |
| Rekap kavling | `GET /documents/lot-recap` | root, back_office | Rekap per tipe kavling |

### 7.8 Modul User Management

**Controller:** `UserController`  

| Fitur | Role | Deskripsi |
|-------|------|-----------|
| List user | root | Semua user sistem |
| Detail user | root | Info user + role |
| Tambah user | root | Buat user baru |
| Edit user | root | Update data user |
| Reset password | root | Reset ke password temporary |
| Nonaktifkan user | root | Soft delete / is_active = false |
| Log aktivitas user | root | Riwayat login + aksi |

### 7.9 Modul Piutang

**Controller:** `ReceivableController`  

| Fitur | Role | Deskripsi |
|-------|------|-----------|
| Snapshot piutang | root, back_office | Rekam outstanding akhir bulan |
| Analisis umur piutang | root, back_office | Kategorisasi <30/30-60/60-90/>90 hari |
| Daftar piutang | root, back_office | Per pelanggan / per klaster |

---

## 8. Alur Bisnis (Business Flow)

### 8.1 Siklus Penagihan Bulanan

```
                    SIKLUS PENAGIHAN BULANAN
┌───────────────────────────────────────────────────────────────┐
│                                                               │
│  1. GENERATE    2. APPROVAL     3. PEMBAYARAN   4. LAPORAN    │
│  ──────────     ──────────      ──────────       ────────     │
│  Back Office    Back Office     Loket/BO         Back Office  │
│  /Root          /Root           /Root            /Root        │
│                                                               │
│  POST           POST            POST             GET          │
│  /billings      /billings/      /payments        /reports/    │
│  /prepare       {id}/approve    /process         monthly      │
│                                                               │
│  [status: 01]   [approved_by    [status: 02]    [aggregasi]   │
│  [approved: NO] = user_id]      [paid_at = now]              │
│                 [status: 01]    [receipt = GD..]             │
└───────────────────────────────────────────────────────────────┘
```

### 8.2 Flow Pembayaran Loket (Detail)

```
Request: POST /api/v1/payments/process
Body: {
    customer_id: "DO001",
    billing_ids: [101, 102, 103],
    payment_method_id: "C",
    loket_code: "L01",
    cashier_name: "Rini"
}

PaymentController::process()
    │
    ├── ProcessPaymentRequest::validate()
    │     - customer_id exists & active
    │     - billing_ids semua milik customer_id
    │     - billing_ids semua status '01' (belum bayar)
    │     - billing_ids semua sudah approved
    │
    ├── PaymentService::process($data)
    │     │
    │     ├── PenaltyService::calculate() per billing
    │     │     IF day(now) > 20 AND is_penalty_eligible
    │     │     THEN penalty = amount * 0.03
    │     │
    │     ├── Hitung total:
    │     │     total_billing = SUM(billing.amount)
    │     │     total_penalty = SUM(penalty)
    │     │     grand_total   = total_billing + total_penalty
    │     │
    │     ├── ReceiptService::generateReceiptNumber()
    │     │     Format: GD.YYYY.MM.NNNNNN (DB lock)
    │     │
    │     ├── DB::transaction()
    │     │     ├── INSERT receipts (...)
    │     │     └── UPDATE billings SET
    │     │           status_id = '02',
    │     │           penalty = calculated_penalty,
    │     │           paid_at = now(),
    │     │           receipt_number = '...',
    │     │           processed_by = user_id
    │     │         WHERE id IN (billing_ids)
    │     │
    │     └── event(new PaymentProcessed($receipt, $billingIds))
    │           → SendPaymentNotification (async Job)
    │           → RecordAuditLog (sync)
    │           → UpdateReceivableOnPayment (async)
    │
    └── Return: ReceiptResource($receipt)
          {
            "success": true,
            "data": {
              "receipt_number": "GD.2025.06.000123",
              "grand_total": 550000,
              "pdf_url": "/api/v1/documents/spt/GD.2025.06.000123"
            }
          }
```

### 8.3 Flow Pembatalan (Reversal)

```
STEP 1 — Pengajuan (Loket/Back Office):
  POST /api/v1/reversals
  Body: { receipt_number, reason }
  → INSERT reversals (status: pending)

STEP 2 — Review (Back Office/Root):
  GET /api/v1/reversals?status=pending  [daftar pending]

STEP 3a — Approve:
  POST /api/v1/reversals/{id}/approve
  Body: { review_notes }
  → ReversalService::approve()
        DB::transaction()
          ├── UPDATE billings SET
          │     status_id = '01',
          │     paid_at = NULL,
          │     receipt_number = NULL,
          │     penalty = 0,
          │     processed_by = NULL
          │   WHERE receipt_number = '...'
          ├── DELETE FROM receipts WHERE number = '...'
          │   (atau UPDATE status = 'cancelled')
          └── UPDATE reversals SET status = 'approved'
        event(new ReversalApproved($reversal))

STEP 3b — Reject:
  POST /api/v1/reversals/{id}/reject
  Body: { review_notes }
  → UPDATE reversals SET status = 'rejected'
  → event(new ReversalRejected)
```

### 8.4 Flow Konversi Properti (Kavling → Bangunan)

```
POST /api/v1/customers/{id}/convert-property
Body: { property_type_id: "B", notes: "Unit selesai dibangun" }

CustomerController::convertProperty()
    │
    ├── Validasi: customer.property_type_id harus 'K'
    │
    ├── CustomerService::convertProperty($customerId, data)
    │     ├── UPDATE customers SET
    │     │     property_type_id = 'B',
    │     │     handover_date = now(),
    │     │     updated_by = auth_user_id
    │     │
    │     └── AuditService::log('convert_property', customer)
    │
    └── Return: CustomerResource($customer)
```

---

## 9. Autentikasi & Otorisasi

### 9.1 Laravel Sanctum

Sistem menggunakan **Laravel Sanctum** dengan **Personal Access Token** (bukan session cookie). Setiap login menghasilkan token Bearer yang wajib disertakan di header setiap request.

```
POST /api/v1/auth/login
  → AuthService::login()
      ├── Validasi credentials (username + password bcrypt)
      ├── Cek user is_active = true
      ├── Revoke semua token lama (opsional, konfigurabel)
      ├── $user->createToken('grandduta-api', ['*'])->plainTextToken
      ├── Update last_login_at, last_login_ip
      └── Return token + user info + permissions
```

**Token Header:**
```
Authorization: Bearer {token}
```

**Token Expiry:** Konfigurasikan di `config/sanctum.php`:
```php
'expiration' => 480,  // 480 menit = 8 jam
```

### 9.2 Konfigurasi Sanctum

```php
// config/sanctum.php
return [
    'stateful' => [],               // API-only, tidak ada stateful domain
    'expiration' => 480,            // 8 jam
    'token_prefix' => 'gd_',        // Prefix token untuk identifikasi
    'middleware' => [
        'authenticate_session' => null,
        'encrypt_cookies' => null,
        'validate_csrf_token' => null,
    ],
];
```

### 9.3 Spatie Laravel Permission

RBAC dikelola menggunakan package **spatie/laravel-permission**.

**Roles:**
```
root
back_office
loket
cs
```

**Permissions (per resource × aksi):**

```
# Customer
customers.view
customers.create
customers.update
customers.delete
customers.convert-property

# Cluster
clusters.view
clusters.update-rate

# Billing
billings.view
billings.prepare
billings.prepare-special
billings.prepare-back
billings.approve
billings.update
billings.delete

# Payment
payments.view
payments.process

# Installment
installments.view
installments.create

# Reversal
reversals.view
reversals.submit
reversals.approve

# Report
reports.view

# Document
documents.generate

# User Management
users.view
users.create
users.update
users.delete

# Audit
audit.view
```

**Mapping Role → Permission:**

```php
// RolePermissionSeeder.php

$roles = [
    'root' => ['*'],  // Semua permission

    'back_office' => [
        'customers.view', 'customers.create', 'customers.update', 'customers.convert-property',
        'clusters.view', 'clusters.update-rate',
        'billings.view', 'billings.prepare', 'billings.prepare-special',
        'billings.prepare-back', 'billings.approve', 'billings.update',
        'payments.view', 'payments.process',
        'installments.view', 'installments.create',
        'reversals.view', 'reversals.submit', 'reversals.approve',
        'reports.view',
        'documents.generate',
    ],

    'loket' => [
        'customers.view',
        'clusters.view',
        'billings.view',
        'payments.view', 'payments.process',
        'installments.view', 'installments.create',
        'reversals.view', 'reversals.submit',
        'documents.generate',
    ],

    'cs' => [
        'customers.view',
        'clusters.view',
        'billings.view',
    ],
];
```

### 9.4 Middleware Stack untuk Route API

```php
// routes/api.php

Route::prefix('v1')->group(function () {

    // Public routes (tidak perlu auth)
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Protected routes
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

        // Customer routes
        Route::apiResource('customers', CustomerController::class);
        Route::post('customers/{id}/convert-property', [CustomerController::class, 'convertProperty']);

        // Cluster routes
        Route::get('clusters', [ClusterController::class, 'index']);
        Route::get('clusters/{id}', [ClusterController::class, 'show']);
        Route::put('clusters/{id}', [ClusterController::class, 'update']);

        // Billing routes
        Route::apiResource('billings', BillingController::class);
        Route::post('billings/prepare-monthly', [BillingController::class, 'prepareMonthly']);
        Route::post('billings/prepare-special', [BillingController::class, 'prepareSpecial']);
        Route::post('billings/prepare-back', [BillingController::class, 'prepareBack']);
        Route::post('billings/{id}/approve', [BillingApprovalController::class, 'approve']);
        Route::post('billings/approve-batch', [BillingApprovalController::class, 'approveBatch']);
        Route::post('billings/{id}/revoke-approval', [BillingApprovalController::class, 'revokeApproval']);
        Route::get('billings/pending-approval', [BillingApprovalController::class, 'pendingApproval']);

        // Payment routes
        Route::get('payments/search', [PaymentController::class, 'searchUnpaid']);
        Route::post('payments/preview', [PaymentController::class, 'preview']);
        Route::post('payments/process', [PaymentController::class, 'process']);
        Route::get('payments/receipts', [PaymentController::class, 'listReceipts']);
        Route::get('payments/receipts/{number}', [PaymentController::class, 'showReceipt']);

        // Installment routes
        Route::apiResource('installments', InstallmentController::class)->only(['index','store','show']);
        Route::get('customers/{id}/installments', [InstallmentController::class, 'byCustomer']);

        // Back payment
        Route::post('back-payments/process', [BackPaymentController::class, 'process']);

        // Reversal routes
        Route::apiResource('reversals', ReversalController::class)->only(['index','store','show']);
        Route::post('reversals/{id}/approve', [ReversalController::class, 'approve']);
        Route::post('reversals/{id}/reject', [ReversalController::class, 'reject']);

        // Receivable routes
        Route::get('receivables', [ReceivableController::class, 'index']);
        Route::get('receivables/aging', [ReceivableController::class, 'aging']);
        Route::post('receivables/snapshot', [ReceivableController::class, 'snapshot']);

        // Report routes
        Route::prefix('reports')->group(function () {
            Route::get('dashboard', [ReportController::class, 'dashboard']);
            Route::get('monthly', [ReportController::class, 'monthly']);
            Route::get('daily-receipt', [ReportController::class, 'dailyReceipt']);
            Route::get('reconciliation', [ReportController::class, 'reconciliation']);
            Route::get('collector', [ReportController::class, 'collector']);
            Route::get('receivables', [ReportController::class, 'receivables']);
        });

        // Document (PDF) routes
        Route::prefix('documents')->group(function () {
            Route::get('spt/{receiptNumber}', [DocumentController::class, 'spt']);
            Route::get('spk/{billingId}', [DocumentController::class, 'spk']);
            Route::get('billing-recap', [DocumentController::class, 'billingRecap']);
            Route::get('customer-list', [DocumentController::class, 'customerList']);
            Route::get('cluster-recap', [DocumentController::class, 'clusterRecap']);
        });

        // User Management (root only)
        Route::apiResource('users', UserController::class);
        Route::post('users/{id}/reset-password', [UserController::class, 'resetPassword']);
        Route::post('users/{id}/toggle-status', [UserController::class, 'toggleStatus']);

        // Lookup / reference data
        Route::prefix('lookup')->group(function () {
            Route::get('regencies', [LookupController::class, 'regencies']);
            Route::get('districts', [LookupController::class, 'districts']);
            Route::get('property-types', [LookupController::class, 'propertyTypes']);
            Route::get('customer-statuses', [LookupController::class, 'customerStatuses']);
            Route::get('payment-methods', [LookupController::class, 'paymentMethods']);
        });

        // Audit log
        Route::get('audit-logs', [AuditController::class, 'index']);

    });
});
```

---

## 10. Sistem Role & Permission (RBAC)

### 10.1 Matriks Akses Lengkap

| Endpoint | Root | Back Office | Loket | CS |
|----------|:----:|:-----------:|:-----:|:--:|
| **AUTH** | | | | |
| `POST /auth/login` | ✓ | ✓ | ✓ | ✓ |
| `POST /auth/logout` | ✓ | ✓ | ✓ | ✓ |
| `GET /auth/me` | ✓ | ✓ | ✓ | ✓ |
| `POST /auth/change-password` | ✓ | ✓ | ✓ | ✓ |
| **CLUSTER** | | | | |
| `GET /clusters` | ✓ | ✓ | ✓ | ✓ |
| `PUT /clusters/{id}` | ✓ | ✓ | — | — |
| **CUSTOMER** | | | | |
| `GET /customers` | ✓ | ✓ | ✓ | ✓ |
| `POST /customers` | ✓ | ✓ | — | — |
| `PUT /customers/{id}` | ✓ | ✓ | — | — |
| `DELETE /customers/{id}` | ✓ | — | — | — |
| `POST /customers/{id}/convert-property` | ✓ | ✓ | — | — |
| **BILLING** | | | | |
| `GET /billings` | ✓ | ✓ | ✓ | ✓ |
| `POST /billings/prepare-monthly` | ✓ | ✓ | — | — |
| `POST /billings/prepare-special` | ✓ | ✓ | — | — |
| `POST /billings/prepare-back` | ✓ | ✓ | — | — |
| `POST /billings/{id}/approve` | ✓ | ✓ | — | — |
| `POST /billings/approve-batch` | ✓ | ✓ | — | — |
| **PAYMENT** | | | | |
| `GET /payments/search` | ✓ | ✓ | ✓ | — |
| `POST /payments/preview` | ✓ | ✓ | ✓ | — |
| `POST /payments/process` | ✓ | ✓ | ✓ | — |
| `GET /payments/receipts` | ✓ | ✓ | ✓ | — |
| **INSTALLMENT** | | | | |
| `POST /installments` | ✓ | ✓ | ✓ | — |
| `GET /installments` | ✓ | ✓ | ✓ | — |
| **REVERSAL** | | | | |
| `POST /reversals` | ✓ | ✓ | ✓ | — |
| `POST /reversals/{id}/approve` | ✓ | ✓ | — | — |
| `POST /reversals/{id}/reject` | ✓ | ✓ | — | — |
| **REPORT** | | | | |
| `GET /reports/*` | ✓ | ✓ | — | — |
| **DOCUMENT** | | | | |
| `GET /documents/spt/{receipt}` | ✓ | ✓ | ✓ | — |
| `GET /documents/spk/{billing}` | ✓ | ✓ | ✓ | — |
| `GET /documents/billing-recap` | ✓ | ✓ | — | — |
| **USER MANAGEMENT** | | | | |
| `GET /users` | ✓ | — | — | — |
| `POST /users` | ✓ | — | — | — |
| `PUT /users/{id}` | ✓ | — | — | — |
| `DELETE /users/{id}` | ✓ | — | — | — |
| **AUDIT** | | | | |
| `GET /audit-logs` | ✓ | — | — | — |

### 10.2 Implementasi Permission Check di Controller

```php
// Cara 1: via Policy (direkomendasikan)
public function store(StoreCustomerRequest $request): JsonResponse
{
    $this->authorize('create', Customer::class);
    // ...
}

// Cara 2: via Permission langsung
public function prepareMonthly(PrepareBillingRequest $request): JsonResponse
{
    if (!$request->user()->can('billings.prepare')) {
        abort(403, 'Anda tidak memiliki akses untuk menyiapkan tagihan.');
    }
    // ...
}

// Cara 3: via Route middleware
Route::post('billings/prepare-monthly', ...)
    ->middleware('permission:billings.prepare');
```

---

## 11. Standar Format Request & Response API

### 11.1 Base URL

```
Production : https://api.grandduta.com/api/v1
Staging    : https://api-staging.grandduta.com/api/v1
Development: http://localhost:8000/api/v1
```

### 11.2 Request Headers Wajib

```http
Content-Type: application/json
Accept: application/json
Authorization: Bearer {token}          # Wajib untuk protected routes
X-Request-ID: {uuid}                   # Opsional, untuk tracing
```

### 11.3 Format Response Standar

#### Response Sukses (Single Resource)

```json
{
    "success": true,
    "message": "Data berhasil diambil",
    "data": {
        "id": "DO001",
        "name": "Budi Santoso",
        "cluster": {
            "id": "DO",
            "name": "Dolomite"
        }
    },
    "meta": null
}
```

#### Response Sukses (Collection / Paginated)

```json
{
    "success": true,
    "message": "Data berhasil diambil",
    "data": [
        { "id": "DO001", "name": "Budi Santoso" },
        { "id": "DO002", "name": "Siti Aminah" }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 245,
        "last_page": 13,
        "from": 1,
        "to": 20
    }
}
```

#### Response Error (Validasi)

```json
{
    "success": false,
    "message": "Data tidak valid",
    "errors": {
        "customer_id": ["ID Pelanggan wajib diisi."],
        "billing_ids": ["Minimal pilih 1 tagihan."],
        "billing_ids.0": ["Tagihan ID 99 tidak ditemukan."]
    },
    "data": null
}
```

#### Response Error (Server)

```json
{
    "success": false,
    "message": "Terjadi kesalahan pada server.",
    "error_code": "ERR_500",
    "data": null
}
```

### 11.4 HTTP Status Codes

| Code | Kondisi |
|------|---------|
| `200 OK` | Request berhasil (GET, PUT, PATCH) |
| `201 Created` | Resource berhasil dibuat (POST) |
| `204 No Content` | Berhasil tanpa response body (DELETE) |
| `400 Bad Request` | Request tidak valid (bukan validasi form) |
| `401 Unauthorized` | Token tidak ada atau expired |
| `403 Forbidden` | Token valid tapi tidak ada akses |
| `404 Not Found` | Resource tidak ditemukan |
| `409 Conflict` | Konflik data (duplikat tagihan, dll.) |
| `422 Unprocessable Entity` | Validasi form gagal |
| `429 Too Many Requests` | Rate limit terlampaui |
| `500 Internal Server Error` | Error server |

### 11.5 Trait ApiResponse

```php
<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(
        mixed $data = null,
        string $message = 'Berhasil',
        int $code = 200,
        array $meta = null
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'meta'    => $meta,
        ], $code);
    }

    protected function created(mixed $data, string $message = 'Data berhasil ditambahkan'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function error(
        string $message = 'Terjadi kesalahan',
        int $code = 500,
        mixed $errors = null,
        string $errorCode = null
    ): JsonResponse {
        return response()->json([
            'success'    => false,
            'message'    => $message,
            'errors'     => $errors,
            'error_code' => $errorCode,
            'data'       => null,
        ], $code);
    }

    protected function notFound(string $message = 'Data tidak ditemukan'): JsonResponse
    {
        return $this->error($message, 404, null, 'ERR_NOT_FOUND');
    }

    protected function forbidden(string $message = 'Akses ditolak'): JsonResponse
    {
        return $this->error($message, 403, null, 'ERR_FORBIDDEN');
    }

    protected function paginated(
        $collection,
        string $message = 'Data berhasil diambil'
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $collection->items(),
            'meta'    => [
                'current_page' => $collection->currentPage(),
                'per_page'     => $collection->perPage(),
                'total'        => $collection->total(),
                'last_page'    => $collection->lastPage(),
                'from'         => $collection->firstItem(),
                'to'           => $collection->lastItem(),
            ],
        ]);
    }
}
```

### 11.6 Resources (API Transformer)

#### `CustomerResource`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'cluster'               => [
                'id'   => $this->cluster->id,
                'name' => $this->cluster->name,
                'rate' => $this->cluster->monthly_rate,
            ],
            'block'                 => $this->block,
            'lot_number'            => $this->lot_number,
            'full_address'          => $this->full_address,
            'property_type'         => [
                'id'   => $this->property_type_id,
                'name' => $this->propertyType?->name,
            ],
            'status'                => [
                'id'   => $this->status_id,
                'name' => $this->status?->name,
            ],
            'occupancy'             => $this->occupancy_id,
            'phone'                 => $this->phone,
            'telephone'             => $this->telephone,
            'email'                 => $this->email,
            'id_card_address'       => $this->id_card_address,
            'district'              => $this->when($this->district, [
                'id'   => $this->district?->id,
                'name' => $this->district?->name,
            ]),
            'building_area'         => $this->building_area,
            'land_area'             => $this->land_area,
            'handover_date'         => $this->handover_date?->format('Y-m-d'),
            'is_penalty_eligible'   => $this->is_penalty_eligible,
            'is_discount_eligible'  => $this->is_discount_eligible,
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

#### `BillingResource`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BillingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'customer_id'           => $this->customer_id,
            'customer_name'         => $this->customer?->name,
            'cluster_name'          => $this->customer?->cluster?->name,
            'period'                => [
                'year'  => $this->year,
                'month' => $this->month,
                'label' => $this->period_label,
            ],
            'amount'                => $this->amount,
            'penalty'               => $this->penalty,
            'discount'              => $this->discount,
            'total'                 => $this->amount + $this->penalty - $this->discount,
            'status'                => [
                'id'   => $this->status_id,
                'name' => $this->status?->name,
            ],
            'billing_type'          => $this->billing_type,
            'is_penalty_eligible'   => $this->is_penalty_eligible,
            'approval' => [
                'approved'      => $this->isApproved(),
                'approved_by'   => $this->approver?->name,
                'approved_at'   => $this->approved_at?->toIso8601String(),
                'notes'         => $this->approval_notes,
            ],
            'payment' => $this->when($this->isPaid(), [
                'paid_at'       => $this->paid_at?->toIso8601String(),
                'receipt_number'=> $this->receipt_number,
                'loket_code'    => $this->loket_code,
            ]),
            'spt_print_count'       => $this->spt_print_count,
            'created_at'            => $this->created_at?->toIso8601String(),
        ];
    }
}
```

---

## 12. Standar Validasi Data

### 12.1 Form Request Classes

#### `StoreCustomerRequest`

```php
<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('customers.create');
    }

    public function rules(): array
    {
        return [
            'id'                    => ['required', 'string', 'size:5', 'alpha_num', 'unique:customers,id'],
            'name'                  => ['required', 'string', 'min:3', 'max:100'],
            'cluster_id'            => ['required', 'string', 'size:2', 'exists:clusters,id'],
            'block'                 => ['required', 'string', 'max:5'],
            'lot_number'            => ['required', 'string', 'max:10'],
            'property_type_id'      => ['required', 'string', 'in:B,K,P'],
            'phone'                 => ['nullable', 'string', 'max:20'],
            'telephone'             => ['nullable', 'string', 'max:20'],
            'email'                 => ['nullable', 'email', 'max:100'],
            'id_card_address'       => ['nullable', 'string', 'max:200'],
            'district_id'           => ['nullable', 'string', 'exists:districts,id'],
            'building_area'         => ['nullable', 'numeric', 'min:0'],
            'land_area'             => ['nullable', 'numeric', 'min:0'],
            'handover_date'         => ['nullable', 'date'],
            'occupancy_id'          => ['nullable', 'in:1,2'],
            'status_id'             => ['nullable', 'exists:customer_statuses,id'],
            'is_penalty_eligible'   => ['nullable', 'boolean'],
            'is_discount_eligible'  => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required'           => 'ID Pelanggan wajib diisi.',
            'id.size'               => 'ID Pelanggan harus tepat 5 karakter.',
            'id.alpha_num'          => 'ID Pelanggan hanya boleh berisi huruf dan angka.',
            'id.unique'             => 'ID Pelanggan sudah digunakan.',
            'name.required'         => 'Nama pelanggan wajib diisi.',
            'cluster_id.required'   => 'Klaster wajib dipilih.',
            'cluster_id.exists'     => 'Klaster tidak ditemukan.',
            'property_type_id.in'   => 'Tipe properti harus B (Bangunan), K (Kavling Dev), atau P (Kavling Pelanggan).',
        ];
    }
}
```

#### `ProcessPaymentRequest`

```php
<?php

namespace App\Http\Requests\Payment;

use App\Models\Billing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payments.process');
    }

    public function rules(): array
    {
        return [
            'customer_id'       => ['required', 'string', 'exists:customers,id'],
            'billing_ids'       => ['required', 'array', 'min:1', 'max:24'],
            'billing_ids.*'     => ['required', 'integer', 'exists:billings,id'],
            'payment_method_id' => ['required', 'string', 'in:C,D'],
            'payment_channel_id'=> ['nullable', 'string', 'exists:payment_channels,id'],
            'loket_code'        => ['nullable', 'string', 'max:20'],
            'cashier_name'      => ['required', 'string', 'max:50'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $customerId = $this->input('customer_id');
            $billingIds = $this->input('billing_ids', []);

            foreach ($billingIds as $billingId) {
                $billing = Billing::find($billingId);

                if (!$billing) continue;

                // Validasi billing milik customer ini
                if ($billing->customer_id !== $customerId) {
                    $validator->errors()->add(
                        "billing_ids.{$billingId}",
                        "Tagihan ID {$billingId} bukan milik pelanggan ini."
                    );
                }

                // Validasi belum dibayar
                if ($billing->isPaid()) {
                    $validator->errors()->add(
                        "billing_ids.{$billingId}",
                        "Tagihan ID {$billingId} sudah lunas."
                    );
                }

                // Validasi sudah di-approve
                if (!$billing->isApproved()) {
                    $validator->errors()->add(
                        "billing_ids.{$billingId}",
                        "Tagihan ID {$billingId} belum di-approve."
                    );
                }
            }
        });
    }
}
```

### 12.2 Aturan Validasi Umum

| Field | Aturan |
|-------|--------|
| Customer ID | Tepat 5 karakter alphanumeric, unique |
| Nama | Min 3, Max 100 karakter |
| Email | Format email valid |
| Phone | Numerik, max 20 karakter |
| Tahun | 4 digit, antara 2000–2099 |
| Bulan | Integer 1–12 |
| Amount/Nominal | Numerik positif, max 15 digit, 2 desimal |
| Tanggal | Format Y-m-d atau ISO8601 |
| Cluster ID | Tepat 2 karakter uppercase, exists di clusters |

---

## 13. Standar Penanganan Error & Exception

### 13.1 Custom Exception Classes

```php
// app/Exceptions/

// Business logic exceptions
class BillingAlreadyExistsException extends RuntimeException {}
class BillingAlreadyPaidException extends RuntimeException {}
class BillingNotApprovedException extends RuntimeException {}
class CustomerNotActiveException extends RuntimeException {}
class InvalidPropertyConversionException extends RuntimeException {}
class DuplicateReceiptException extends RuntimeException {}
class ReversalAlreadyProcessedException extends RuntimeException {}
```

### 13.2 Global Exception Handler

```php
<?php

namespace App\Exceptions;

use App\Http\Traits\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler
{
    use ApiResponse;

    public function register(): void
    {
        $this->renderable(function (AuthenticationException $e) {
            return $this->error('Token tidak valid atau sudah kadaluarsa.', 401, null, 'ERR_UNAUTHORIZED');
        });

        $this->renderable(function (AccessDeniedHttpException $e) {
            return $this->error('Anda tidak memiliki akses ke resource ini.', 403, null, 'ERR_FORBIDDEN');
        });

        $this->renderable(function (NotFoundHttpException $e) {
            return $this->error('Data tidak ditemukan.', 404, null, 'ERR_NOT_FOUND');
        });

        $this->renderable(function (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data yang dimasukkan tidak valid.',
                'errors'  => $e->errors(),
                'data'    => null,
            ], 422);
        });

        $this->renderable(function (BillingAlreadyExistsException $e) {
            return $this->error($e->getMessage(), 409, null, 'ERR_BILLING_EXISTS');
        });

        $this->renderable(function (BillingAlreadyPaidException $e) {
            return $this->error($e->getMessage(), 409, null, 'ERR_BILLING_PAID');
        });

        $this->renderable(function (\Throwable $e) {
            if (app()->environment('production')) {
                return $this->error('Terjadi kesalahan sistem. Silakan coba beberapa saat lagi.', 500, null, 'ERR_SERVER');
            }
            // Di development, tampilkan detail error
            return $this->error($e->getMessage(), 500, [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ], 'ERR_SERVER');
        });
    }
}
```

### 13.3 Error Code Reference

| Error Code | HTTP | Deskripsi |
|------------|------|-----------|
| `ERR_UNAUTHORIZED` | 401 | Token tidak valid/expired |
| `ERR_FORBIDDEN` | 403 | Tidak ada akses |
| `ERR_NOT_FOUND` | 404 | Resource tidak ditemukan |
| `ERR_VALIDATION` | 422 | Validasi input gagal |
| `ERR_BILLING_EXISTS` | 409 | Tagihan periode ini sudah ada |
| `ERR_BILLING_PAID` | 409 | Tagihan sudah lunas |
| `ERR_BILLING_NOT_APPROVED` | 409 | Tagihan belum di-approve |
| `ERR_CUSTOMER_INACTIVE` | 409 | Pelanggan tidak aktif |
| `ERR_PROPERTY_CONVERSION` | 409 | Konversi properti tidak valid |
| `ERR_REVERSAL_PROCESSED` | 409 | Pembatalan sudah diproses |
| `ERR_DUPLICATE_RECEIPT` | 409 | Nomor kuitansi duplikat |
| `ERR_RATE_LIMIT` | 429 | Rate limit terlampaui |
| `ERR_SERVER` | 500 | Server error |

---

## 14. Logging, Monitoring & Audit Trail

### 14.1 Laravel Logging Configuration

```php
// config/logging.php
'channels' => [
    'stack' => [
        'driver'   => 'stack',
        'channels' => ['daily', 'slack'],  // Stack: file + Slack notif
    ],
    'daily' => [
        'driver' => 'daily',
        'path'   => storage_path('logs/grandduta.log'),
        'level'  => 'info',
        'days'   => 90,             // Simpan 90 hari
    ],
    'payment' => [
        'driver' => 'daily',
        'path'   => storage_path('logs/payment.log'),
        'level'  => 'info',
        'days'   => 365,            // Log payment simpan 1 tahun
    ],
    'audit' => [
        'driver' => 'daily',
        'path'   => storage_path('logs/audit.log'),
        'level'  => 'info',
        'days'   => 365,
    ],
],
```

### 14.2 AuditService

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuditService
{
    public function log(
        string $action,
        string $module,
        string $recordId = null,
        array $oldData = null,
        array $newData = null
    ): void {
        $userId = Auth::id();

        AuditLog::create([
            'user_id'    => $userId,
            'action'     => $action,
            'module'     => $module,
            'record_id'  => $recordId,
            'old_data'   => $oldData ? json_encode($oldData) : null,
            'new_data'   => $newData ? json_encode($newData) : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Juga log ke file untuk backup
        Log::channel('audit')->info("[$action] [$module] Record: $recordId", [
            'user_id' => $userId,
        ]);
    }

    public function logRequest(Request $request, $response): void
    {
        // Hanya log jika response sukses (2xx)
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $method = $request->method();
            $path = $request->path();
            $action = match ($method) {
                'POST'   => 'CREATE',
                'PUT','PATCH' => 'UPDATE',
                'DELETE' => 'DELETE',
                default  => 'ACTION',
            };

            $this->log($action, $path, null, null, $request->except(['password', 'password_confirmation']));
        }
    }
}
```

### 14.3 Jenis Event yang Di-audit

| Event | Module | Action | Data Dicatat |
|-------|--------|--------|--------------|
| Login berhasil | auth | LOGIN | user_id, ip |
| Login gagal | auth | LOGIN_FAILED | username, ip |
| Logout | auth | LOGOUT | user_id |
| Tambah pelanggan | customers | CREATE | Data lengkap |
| Edit pelanggan | customers | UPDATE | Before & after |
| Hapus pelanggan | customers | DELETE | Data sebelum hapus |
| Konversi properti | customers | CONVERT | Before & after |
| Generate tagihan | billings | CREATE | Jumlah tagihan, periode |
| Approve tagihan | billings | APPROVE | Billing IDs |
| Proses pembayaran | payments | PAYMENT | Receipt + total |
| Input cicilan | installments | CREATE | Nominal, customer |
| Ajukan pembatalan | reversals | SUBMIT | Receipt number |
| Approve pembatalan | reversals | APPROVE | Receipt number |
| Tambah user | users | CREATE | Username, role |
| Reset password | users | RESET_PASSWORD | Username |

---

## 15. Caching & Optimasi Performa

### 15.1 Cache Strategy

```php
// config/cache.php
'default' => env('CACHE_DRIVER', 'redis'),

'stores' => [
    'redis' => [
        'driver'     => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
    ],
],
```

### 15.2 Cache Keys & TTL

| Data | Cache Key | TTL | Invalidasi |
|------|-----------|-----|-----------|
| Daftar klaster + tarif | `clusters:all` | 24 jam | Update tarif |
| Lookup: property types | `lookup:property_types` | 7 hari | Manual |
| Lookup: customer statuses | `lookup:customer_statuses` | 7 hari | Manual |
| Dashboard summary | `dashboard:summary:{user_id}` | 5 menit | Setiap transaksi |
| Data pelanggan | `customer:{id}` | 1 jam | Setiap update |
| Tagihan pending approval | `billings:pending_approval` | 10 menit | Setiap approve |
| Laporan bulanan | `report:monthly:{year}:{month}` | 1 jam | Setiap pembayaran |

### 15.3 Cache Implementation

```php
// ClusterRepository.php
public function all(): Collection
{
    return Cache::remember('clusters:all', now()->addDay(), function () {
        return Cluster::where('is_active', true)->orderBy('name')->get();
    });
}

// Invalidasi cache saat update tarif
public function updateRate(string $clusterId, float $rate): Cluster
{
    $cluster = Cluster::findOrFail($clusterId);
    $cluster->update(['monthly_rate' => $rate]);
    Cache::forget('clusters:all');
    return $cluster;
}
```

### 15.4 Query Optimization

```php
// Gunakan eager loading untuk mencegah N+1
Customer::with(['cluster', 'status', 'propertyType', 'district.regency'])
    ->active()
    ->paginate(20);

// Gunakan chunk untuk proses data besar
Customer::active()->chunk(200, function ($customers) use ($year, $month) {
    foreach ($customers as $customer) {
        // proses generate billing
    }
});

// Gunakan lazy collection untuk memory efficiency
Customer::active()->lazy()->each(function ($customer) {
    // proses satu per satu
});
```

### 15.5 Rate Limiting

```php
// config/grandduta.php
'rate_limits' => [
    'api'           => '300,1',    // 300 req/menit (authenticated)
    'public'        => '60,1',     // 60 req/menit (unauthenticated)
    'billing_gen'   => '5,1',      // 5 req/menit (generate billing endpoint)
    'pdf_export'    => '20,1',     // 20 req/menit (PDF generation)
];

// routes/api.php
Route::middleware(['auth:sanctum', 'throttle:300,1'])->group(function () { ... });
Route::post('billings/prepare-monthly')->middleware('throttle:5,1');
Route::get('documents/*')->middleware('throttle:20,1');
```

---

## 16. Upload & Manajemen File

### 16.1 Konfigurasi Storage

```php
// config/filesystems.php
'disks' => [
    'local' => [
        'driver' => 'local',
        'root'   => storage_path('app'),
    ],
    'documents' => [
        'driver' => 'local',
        'root'   => storage_path('app/documents'),
        'url'    => env('APP_URL') . '/storage/documents',
    ],
    's3' => [
        'driver'   => 's3',
        'key'      => env('AWS_ACCESS_KEY_ID'),
        'secret'   => env('AWS_SECRET_ACCESS_KEY'),
        'region'   => env('AWS_DEFAULT_REGION', 'ap-southeast-1'),
        'bucket'   => env('AWS_BUCKET'),
        'url'      => env('AWS_URL'),
    ],
],
```

### 16.2 PDF Generation (DocumentService)

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Receipt;
use App\Models\Billing;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class DocumentService
{
    /**
     * Generate PDF SPT (Surat Pembayaran Tunai).
     * Update counter cetak pada billing yang terkait.
     */
    public function generateSpt(Receipt $receipt): Response
    {
        $receipt->load('customer.cluster', 'paymentMethod');

        // Update SPT print counter di semua billing terkait
        Billing::where('receipt_number', $receipt->number)
            ->increment('spt_print_count');

        $pdf = Pdf::loadView('documents.spt', [
            'receipt' => $receipt,
            'date'    => now()->format('d F Y'),
        ])->setPaper('a5', 'portrait');

        return $pdf->download("SPT-{$receipt->number}.pdf");
    }

    /**
     * Generate PDF SPK (Surat Pemberitahuan Kredit / tagihan belum lunas).
     */
    public function generateSpk(Billing $billing): Response
    {
        $billing->load('customer.cluster');

        $pdf = Pdf::loadView('documents.spk', [
            'billing' => $billing,
            'date'    => now()->format('d F Y'),
        ])->setPaper('a5', 'portrait');

        return $pdf->download("SPK-{$billing->customer_id}-{$billing->year}-{$billing->month}.pdf");
    }

    /**
     * Generate rekap tagihan massal.
     */
    public function generateBillingRecap(array $filters): Response
    {
        // Ambil data sesuai filter
        $data = Billing::with('customer.cluster')
            ->when($filters['year'] ?? null, fn($q, $year) => $q->where('year', $year))
            ->when($filters['month'] ?? null, fn($q, $month) => $q->where('month', $month))
            ->when($filters['cluster_id'] ?? null, fn($q, $id) => $q->whereHas('customer', fn($q) => $q->where('cluster_id', $id)))
            ->orderBy('year')->orderBy('month')
            ->get();

        $pdf = Pdf::loadView('documents.billing-recap', compact('data', 'filters'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('Rekap-Tagihan.pdf');
    }
}
```

### 16.3 Blade Template untuk PDF

```
resources/views/documents/
├── spt.blade.php          # Surat Pembayaran Tunai
├── spk.blade.php          # Surat Pemberitahuan Kredit
├── billing-recap.blade.php
├── customer-list.blade.php
└── partials/
    ├── header.blade.php   # Logo + info perusahaan
    └── footer.blade.php   # Tanda tangan + nomor dokumen
```

---

*Lanjutan di TECHNICAL_SPEC_PART3.md*
