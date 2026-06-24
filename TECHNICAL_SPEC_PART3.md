# GRANDDUTA — Technical Spec Part 3
## Dokumentasi API Lengkap (Section 18)

---

## 18. Dokumentasi API Lengkap

**Base URL:** `https://api.grandduta.com/api/v1`  
**Format:** JSON  
**Autentikasi:** Bearer Token (Laravel Sanctum)

---

### 18.1 AUTH — Autentikasi

---

#### `POST /auth/login`

**Deskripsi:** Login ke sistem, mendapatkan Bearer token.  
**Akses:** Public (tanpa token)

**Request Headers:**
```http
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
    "username": "string|required",
    "password": "string|required"
}
```

**Validasi:**
- `username`: wajib, string
- `password`: wajib, string, min 6 karakter

**Contoh Request:**
```bash
curl -X POST https://api.grandduta.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"brata","password":"secret123"}'
```

**Response 200 — Sukses:**
```json
{
    "success": true,
    "message": "Login berhasil",
    "data": {
        "token": "gd_1|AbCdEf1234567890...",
        "token_type": "Bearer",
        "expires_in": 28800,
        "user": {
            "id": 1,
            "name": "Brata Kusuma",
            "username": "brata",
            "email": "brata@grandduta.com",
            "role": "root",
            "permissions": ["customers.create", "billings.prepare", "..."]
        }
    },
    "meta": null
}
```

**Response 401 — Gagal (credentials salah):**
```json
{
    "success": false,
    "message": "Username atau password salah.",
    "errors": null,
    "error_code": "ERR_UNAUTHORIZED",
    "data": null
}
```

**Response 403 — User tidak aktif:**
```json
{
    "success": false,
    "message": "Akun Anda telah dinonaktifkan. Hubungi administrator.",
    "error_code": "ERR_FORBIDDEN",
    "data": null
}
```

---

#### `POST /auth/logout`

**Deskripsi:** Logout, revoke token aktif.  
**Akses:** Semua role (authenticated)

**Request Headers:**
```http
Authorization: Bearer {token}
```

**Response 200:**
```json
{
    "success": true,
    "message": "Logout berhasil.",
    "data": null
}
```

---

#### `GET /auth/me`

**Deskripsi:** Informasi user yang sedang login.  
**Akses:** Semua role

**Response 200:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Brata Kusuma",
        "username": "brata",
        "role": "root",
        "permissions": ["customers.create", "..."],
        "last_login_at": "2026-06-02T08:00:00+07:00"
    }
}
```

---

#### `POST /auth/change-password`

**Deskripsi:** Ganti password user yang sedang login.  
**Akses:** Semua role

**Request Body:**
```json
{
    "current_password": "string|required",
    "new_password": "string|required|min:8",
    "new_password_confirmation": "string|required"
}
```

**Response 200:**
```json
{
    "success": true,
    "message": "Password berhasil diubah."
}
```

**Response 422 — Password lama salah:**
```json
{
    "success": false,
    "message": "Data tidak valid.",
    "errors": {
        "current_password": ["Password saat ini tidak sesuai."]
    }
}
```

---

### 18.2 CLUSTER — Master Data Klaster

---

#### `GET /clusters`

**Deskripsi:** Daftar semua klaster beserta tarif bulanan.  
**Akses:** Semua role

**Response 200:**
```json
{
    "success": true,
    "data": [
        {
            "id": "DO",
            "name": "Dolomite",
            "monthly_rate": 350000.00,
            "is_active": true
        },
        {
            "id": "GA",
            "name": "Garnet",
            "monthly_rate": 300000.00,
            "is_active": true
        }
    ]
}
```

---

#### `GET /clusters/{id}`

**Deskripsi:** Detail satu klaster.  
**Akses:** Semua role  
**Path Parameter:** `id` — 2 karakter kode klaster (contoh: `DO`)

**Response 200:**
```json
{
    "success": true,
    "data": {
        "id": "DO",
        "name": "Dolomite",
        "monthly_rate": 350000.00,
        "description": null,
        "is_active": true,
        "updated_at": "2025-01-15T10:00:00+07:00"
    }
}
```

**Response 404:**
```json
{
    "success": false,
    "message": "Klaster tidak ditemukan.",
    "error_code": "ERR_NOT_FOUND"
}
```

---

#### `PUT /clusters/{id}`

**Deskripsi:** Update data klaster (terutama tarif bulanan). Perubahan tarif berlaku untuk tagihan yang dibuat setelah update — tagihan yang sudah terbit tidak berubah.  
**Akses:** root, back_office

**Request Body:**
```json
{
    "name": "string|optional|max:50",
    "monthly_rate": "numeric|optional|min:0",
    "description": "string|optional|nullable"
}
```

**Contoh Request:**
```bash
curl -X PUT https://api.grandduta.com/api/v1/clusters/DO \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"monthly_rate": 375000}'
```

**Response 200:**
```json
{
    "success": true,
    "message": "Tarif klaster Dolomite berhasil diupdate. Berlaku untuk tagihan bulan berikutnya.",
    "data": {
        "id": "DO",
        "name": "Dolomite",
        "monthly_rate": 375000.00
    }
}
```

---

### 18.3 CUSTOMER — Master Data Pelanggan

---

#### `GET /customers`

**Deskripsi:** Daftar pelanggan dengan filter dan paginasi.  
**Akses:** Semua role

**Query Parameters:**
| Parameter | Tipe | Wajib | Deskripsi |
|-----------|------|-------|-----------|
| `page` | integer | — | Halaman (default: 1) |
| `per_page` | integer | — | Item per halaman (default: 20, max: 100) |
| `cluster_id` | string | — | Filter per klaster (contoh: `DO`) |
| `status_id` | string | — | Filter status: `AK`, `RK`, `TA` |
| `property_type_id` | string | — | Filter tipe: `B`, `K`, `P` |
| `block` | string | — | Filter per blok |
| `search` | string | — | Cari nama/ID pelanggan |

**Contoh Request:**
```
GET /customers?cluster_id=DO&status_id=AK&page=1&per_page=20
```

**Response 200:**
```json
{
    "success": true,
    "data": [
        {
            "id": "DO001",
            "name": "Budi Santoso",
            "cluster": { "id": "DO", "name": "Dolomite" },
            "block": "A",
            "lot_number": "01",
            "property_type": { "id": "B", "name": "Bangunan" },
            "status": { "id": "AK", "name": "Aktif" },
            "phone": "08123456789",
            "is_penalty_eligible": true
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 245,
        "last_page": 13
    }
}
```

---

#### `POST /customers`

**Deskripsi:** Tambah pelanggan baru.  
**Akses:** root, back_office

**Request Body:**
```json
{
    "id": "DO025",
    "name": "Ahmad Fauzi",
    "cluster_id": "DO",
    "block": "C",
    "lot_number": "15",
    "property_type_id": "B",
    "phone": "08567891234",
    "telephone": null,
    "email": "ahmad@email.com",
    "id_card_address": "Jl. Melati No.5, Bandar Lampung",
    "district_id": "BDL01",
    "building_area": 45.00,
    "land_area": 72.00,
    "handover_date": "2025-03-01",
    "occupancy_id": "1",
    "status_id": "AK",
    "is_penalty_eligible": true,
    "is_discount_eligible": false
}
```

**Validasi:**
- `id`: wajib, tepat 5 karakter alphanumeric, harus unik
- `name`: wajib, 3–100 karakter
- `cluster_id`: wajib, harus ada di tabel clusters
- `block`: wajib
- `lot_number`: wajib
- `property_type_id`: wajib, nilai: `B`, `K`, `P`
- Kombinasi `cluster_id + block + lot_number` harus unik

**Response 201:**
```json
{
    "success": true,
    "message": "Pelanggan berhasil ditambahkan.",
    "data": {
        "id": "DO025",
        "name": "Ahmad Fauzi",
        "cluster": { "id": "DO", "name": "Dolomite" },
        "block": "C",
        "lot_number": "15",
        "full_address": "Blok C/15, Klaster Dolomite"
    }
}
```

**Response 409 — ID duplikat:**
```json
{
    "success": false,
    "message": "Data tidak valid.",
    "errors": {
        "id": ["ID Pelanggan DO025 sudah digunakan."]
    }
}
```

---

#### `GET /customers/{id}`

**Deskripsi:** Detail lengkap satu pelanggan.  
**Akses:** Semua role  
**Path Parameter:** `id` — Customer ID (contoh: `DO001`)

**Response 200:**
```json
{
    "success": true,
    "data": {
        "id": "DO001",
        "name": "Budi Santoso",
        "cluster": {
            "id": "DO",
            "name": "Dolomite",
            "rate": 350000.00
        },
        "block": "A",
        "lot_number": "01",
        "full_address": "Blok A/01, Klaster Dolomite",
        "property_type": { "id": "B", "name": "Bangunan" },
        "status": { "id": "AK", "name": "Aktif" },
        "occupancy": "1",
        "phone": "08123456789",
        "telephone": null,
        "email": "budi@email.com",
        "id_card_address": "Jl. Kenanga No.3",
        "district": { "id": "BDL01", "name": "Kedaton" },
        "building_area": 45.00,
        "land_area": 72.00,
        "handover_date": "2020-06-15",
        "is_penalty_eligible": true,
        "is_discount_eligible": false,
        "created_at": "2020-06-15T09:00:00+07:00",
        "updated_at": "2025-01-10T14:30:00+07:00"
    }
}
```

---

#### `PUT /customers/{id}`

**Deskripsi:** Update data pelanggan.  
**Akses:** root, back_office

**Request Body:** (sama dengan POST, field bersifat opsional kecuali field wajib)

**Response 200:**
```json
{
    "success": true,
    "message": "Data pelanggan berhasil diperbarui.",
    "data": { "...customer object..." }
}
```

---

#### `DELETE /customers/{id}`

**Deskripsi:** Soft delete pelanggan.  
**Akses:** root only

**Response 204:** (No Content)

---

#### `POST /customers/{id}/convert-property`

**Deskripsi:** Konversi tipe properti dari Kavling Developer (K) ke Bangunan (B).  
**Akses:** root, back_office

**Request Body:**
```json
{
    "notes": "Unit selesai dibangun, serah terima per Juni 2025"
}
```

**Validasi:**
- Customer harus ada dan aktif
- Tipe properti saat ini harus `K` (Kavling Developer)

**Response 200:**
```json
{
    "success": true,
    "message": "Properti berhasil dikonversi dari Kavling menjadi Bangunan.",
    "data": {
        "id": "DO025",
        "property_type": { "id": "B", "name": "Bangunan" },
        "handover_date": "2026-06-02"
    }
}
```

**Response 409 — Tipe bukan kavling:**
```json
{
    "success": false,
    "message": "Properti ini bukan bertipe Kavling Developer, tidak dapat dikonversi.",
    "error_code": "ERR_PROPERTY_CONVERSION"
}
```

---

### 18.4 BILLING — Penagihan

---

#### `POST /billings/prepare-monthly`

**Deskripsi:** Generate tagihan bulanan untuk SEMUA pelanggan aktif. Pelanggan yang sudah punya tagihan untuk periode ini akan dilewati (tidak duplikat).  
**Akses:** root, back_office

**Request Body:**
```json
{
    "year": 2025,
    "month": 6
}
```

**Validasi:**
- `year`: wajib, integer, antara 2000–2099
- `month`: wajib, integer, antara 1–12
- Tidak boleh generate tagihan untuk periode di masa depan lebih dari 1 bulan ke depan

**Contoh Request:**
```bash
curl -X POST https://api.grandduta.com/api/v1/billings/prepare-monthly \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"year": 2025, "month": 6}'
```

**Response 200:**
```json
{
    "success": true,
    "message": "Penyiapan tagihan bulanan selesai.",
    "data": {
        "period": {
            "year": 2025,
            "month": 6,
            "label": "Juni 2025"
        },
        "result": {
            "created": 245,
            "skipped": 3,
            "total_active_customers": 248
        }
    }
}
```

**Catatan Implementasi:**
- Proses ini dapat dipanggil via Artisan command juga: `php artisan grandduta:billing:generate 2025 6`
- Untuk pelanggan dengan special rate aktif, gunakan amount dari `special_billing_rates`
- Hasil dilog ke audit trail

---

#### `POST /billings/prepare-special`

**Deskripsi:** Generate tagihan dengan nominal khusus untuk satu pelanggan tertentu.  
**Akses:** root, back_office

**Request Body:**
```json
{
    "customer_id": "DO001",
    "year": 2025,
    "month": 6,
    "amount": 500000,
    "notes": "Tagihan khusus bulan Juni sesuai kesepakatan"
}
```

**Validasi:**
- `customer_id`: wajib, pelanggan harus aktif
- `year`, `month`: wajib, valid
- `amount`: wajib, numeric, min 0
- Kombinasi `customer_id + year + month` belum ada tagihan

**Response 201:**
```json
{
    "success": true,
    "message": "Tagihan khusus berhasil dibuat.",
    "data": {
        "id": 1234,
        "customer_id": "DO001",
        "customer_name": "Budi Santoso",
        "period": { "year": 2025, "month": 6, "label": "Juni 2025" },
        "amount": 500000.00,
        "billing_type": "special",
        "status": { "id": "01", "name": "Belum Bayar" }
    }
}
```

---

#### `POST /billings/prepare-back`

**Deskripsi:** Generate tagihan untuk periode historis (mundur). Digunakan untuk pelanggan yang belum pernah mendapat tagihan untuk bulan-bulan lampau.  
**Akses:** root, back_office

**Request Body:**
```json
{
    "customer_id": "DO001",
    "year": 2023,
    "month": 3,
    "amount": 320000,
    "notes": "Tagihan mundur Maret 2023 yang terlewat"
}
```

**Response 201:**
```json
{
    "success": true,
    "message": "Tagihan mundur berhasil dibuat.",
    "data": {
        "id": 9876,
        "customer_id": "DO001",
        "period": { "year": 2023, "month": 3, "label": "Maret 2023" },
        "amount": 320000.00,
        "billing_type": "back"
    }
}
```

**Response 409 — Tagihan sudah ada:**
```json
{
    "success": false,
    "message": "Tagihan periode Maret 2023 untuk pelanggan DO001 sudah ada.",
    "error_code": "ERR_BILLING_EXISTS"
}
```

---

#### `GET /billings`

**Deskripsi:** Daftar tagihan dengan filter.  
**Akses:** Semua role

**Query Parameters:**
| Parameter | Tipe | Deskripsi |
|-----------|------|-----------|
| `customer_id` | string | Filter per pelanggan |
| `year` | integer | Filter tahun |
| `month` | integer | Filter bulan |
| `status_id` | string | `01` atau `02` |
| `cluster_id` | string | Filter per klaster |
| `billing_type` | string | `regular`, `special`, `back` |
| `is_approved` | boolean | Filter sudah/belum approve |
| `page` | integer | Halaman |
| `per_page` | integer | Default 20 |

**Response 200:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1001,
            "customer_id": "DO001",
            "customer_name": "Budi Santoso",
            "cluster_name": "Dolomite",
            "period": { "year": 2025, "month": 6, "label": "Juni 2025" },
            "amount": 350000.00,
            "penalty": 0.00,
            "billing_type": "regular",
            "status": { "id": "01", "name": "Belum Bayar" },
            "approval": {
                "approved": false,
                "approved_by": null,
                "approved_at": null
            }
        }
    ],
    "meta": { "current_page": 1, "per_page": 20, "total": 245 }
}
```

---

#### `GET /billings/pending-approval`

**Deskripsi:** Daftar tagihan yang belum di-approve, siap untuk di-review.  
**Akses:** root, back_office

**Response 200:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1001,
            "customer_id": "DO001",
            "customer_name": "Budi Santoso",
            "cluster_name": "Dolomite",
            "period": { "year": 2025, "month": 6, "label": "Juni 2025" },
            "amount": 350000.00,
            "created_at": "2025-06-01T08:00:00+07:00"
        }
    ],
    "meta": { "total": 245 }
}
```

---

#### `POST /billings/{id}/approve`

**Deskripsi:** Approve satu tagihan. Setelah di-approve, tagihan bisa dibayar di loket.  
**Akses:** root, back_office  
**Path Parameter:** `id` — Billing ID

**Request Body:**
```json
{
    "notes": "Sudah diverifikasi, disetujui untuk diterbitkan"
}
```

**Response 200:**
```json
{
    "success": true,
    "message": "Tagihan berhasil di-approve.",
    "data": {
        "id": 1001,
        "approval": {
            "approved": true,
            "approved_by": "Brata Kusuma",
            "approved_at": "2026-06-02T09:15:00+07:00",
            "notes": "Sudah diverifikasi, disetujui untuk diterbitkan"
        }
    }
}
```

---

#### `POST /billings/approve-batch`

**Deskripsi:** Approve banyak tagihan sekaligus (batch approval).  
**Akses:** root, back_office

**Request Body:**
```json
{
    "billing_ids": [1001, 1002, 1003, 1004],
    "notes": "Approval batch tagihan Juni 2025"
}
```

**Validasi:**
- `billing_ids`: wajib, array, min 1, max 500
- Semua billing harus belum di-approve

**Response 200:**
```json
{
    "success": true,
    "message": "4 tagihan berhasil di-approve.",
    "data": {
        "approved_count": 4,
        "billing_ids": [1001, 1002, 1003, 1004]
    }
}
```

---

### 18.5 PAYMENT — Pembayaran

---

#### `GET /payments/search`

**Deskripsi:** Cari tagihan yang belum lunas untuk pelanggan tertentu. Endpoint utama untuk operator loket sebelum memproses pembayaran.  
**Akses:** root, back_office, loket

**Query Parameters:**
| Parameter | Tipe | Wajib | Deskripsi |
|-----------|------|-------|-----------|
| `customer_id` | string | ✓ | ID pelanggan |

**Contoh Request:**
```
GET /payments/search?customer_id=DO001
```

**Response 200:**
```json
{
    "success": true,
    "data": {
        "customer": {
            "id": "DO001",
            "name": "Budi Santoso",
            "cluster": "Dolomite",
            "block": "A",
            "lot_number": "01"
        },
        "unpaid_billings": [
            {
                "id": 1001,
                "period": { "year": 2025, "month": 4, "label": "April 2025" },
                "amount": 350000.00,
                "penalty_preview": 10500.00,
                "total_preview": 360500.00,
                "is_penalty_applicable": true,
                "penalty_note": "Denda 3% karena hari ini tanggal 21+"
            },
            {
                "id": 1002,
                "period": { "year": 2025, "month": 5, "label": "Mei 2025" },
                "amount": 350000.00,
                "penalty_preview": 10500.00,
                "total_preview": 360500.00,
                "is_penalty_applicable": true
            }
        ],
        "summary": {
            "total_bills": 2,
            "total_amount": 700000.00,
            "total_penalty": 21000.00,
            "grand_total": 721000.00,
            "penalty_applicable_today": true
        }
    }
}
```

**Catatan Implementasi:**
- `penalty_preview` dihitung real-time berdasarkan hari ini vs tanggal 20
- Response ini bersifat **preview** — nilai aktual dikalkulasi ulang saat `process`
- Hanya menampilkan tagihan yang sudah di-approve

---

#### `POST /payments/preview`

**Deskripsi:** Preview kalkulasi total pembayaran sebelum diproses. Digunakan untuk konfirmasi ke pelanggan.  
**Akses:** root, back_office, loket

**Request Body:**
```json
{
    "customer_id": "DO001",
    "billing_ids": [1001, 1002]
}
```

**Response 200:**
```json
{
    "success": true,
    "data": {
        "customer": {
            "id": "DO001",
            "name": "Budi Santoso"
        },
        "billing_details": [
            {
                "id": 1001,
                "period": "April 2025",
                "amount": 350000.00,
                "penalty": 10500.00,
                "subtotal": 360500.00
            },
            {
                "id": 1002,
                "period": "Mei 2025",
                "amount": 350000.00,
                "penalty": 10500.00,
                "subtotal": 360500.00
            }
        ],
        "total_billing": 700000.00,
        "total_penalty": 21000.00,
        "grand_total": 721000.00,
        "penalty_rate": "3%",
        "penalty_applicable": true,
        "calculated_at": "2026-06-02T10:30:00+07:00"
    }
}
```

---

#### `POST /payments/process`

**Deskripsi:** Proses pembayaran. Endpoint inti transaksi loket — membuat kuitansi dan melunasi tagihan terpilih.  
**Akses:** root, back_office, loket

**Request Body:**
```json
{
    "customer_id": "DO001",
    "billing_ids": [1001, 1002],
    "payment_method_id": "C",
    "payment_channel_id": null,
    "loket_code": "L01",
    "cashier_name": "Rini Pertiwi"
}
```

**Validasi:**
- `customer_id`: wajib, pelanggan harus aktif
- `billing_ids`: wajib, array min 1, semua harus milik `customer_id`, belum lunas, sudah approved
- `payment_method_id`: wajib, `C` atau `D`
- `cashier_name`: wajib

**Response 201:**
```json
{
    "success": true,
    "message": "Pembayaran berhasil diproses.",
    "data": {
        "receipt_number": "GD.2025.06.000123",
        "customer": {
            "id": "DO001",
            "name": "Budi Santoso",
            "cluster": "Dolomite",
            "block": "A",
            "lot_number": "01"
        },
        "transaction_date": "2026-06-02T10:35:00+07:00",
        "billing_periods": "April 2025, Mei 2025",
        "billing_count": 2,
        "total_billing": 700000.00,
        "total_penalty": 21000.00,
        "grand_total": 721000.00,
        "payment_method": "Cash",
        "cashier_name": "Rini Pertiwi",
        "loket_code": "L01",
        "pdf_url": "/api/v1/documents/spt/GD.2025.06.000123"
    }
}
```

**Response 409 — Tagihan sudah lunas:**
```json
{
    "success": false,
    "message": "Tagihan ID 1001 sudah lunas.",
    "error_code": "ERR_BILLING_PAID"
}
```

---

#### `GET /payments/receipts`

**Deskripsi:** Daftar kuitansi (LPP — Laporan Penerimaan Pembayaran).  
**Akses:** root, back_office, loket

**Query Parameters:**
| Parameter | Tipe | Deskripsi |
|-----------|------|-----------|
| `date` | date (Y-m-d) | Filter per tanggal transaksi |
| `customer_id` | string | Filter per pelanggan |
| `year` | integer | Filter tahun |
| `month` | integer | Filter bulan |
| `cashier_name` | string | Filter per kasir |
| `page` | integer | Halaman |

**Contoh Request:**
```
GET /payments/receipts?date=2026-06-02
```

**Response 200:**
```json
{
    "success": true,
    "data": [
        {
            "number": "GD.2025.06.000123",
            "customer": { "id": "DO001", "name": "Budi Santoso" },
            "cluster": "Dolomite",
            "transaction_date": "2026-06-02T10:35:00+07:00",
            "total_billing": 700000.00,
            "total_penalty": 21000.00,
            "grand_total": 721000.00,
            "billing_count": 2,
            "billing_periods": "April 2025, Mei 2025",
            "cashier_name": "Rini Pertiwi",
            "payment_method": "Cash"
        }
    ],
    "meta": {
        "total": 45,
        "daily_grand_total": 18750000.00
    }
}
```

---

#### `GET /payments/receipts/{number}`

**Deskripsi:** Detail satu kuitansi.  
**Akses:** root, back_office, loket  
**Path Parameter:** `number` — Nomor kuitansi (contoh: `GD.2025.06.000123`)

**Response 200:**
```json
{
    "success": true,
    "data": {
        "number": "GD.2025.06.000123",
        "customer": {
            "id": "DO001",
            "name": "Budi Santoso",
            "cluster": "Dolomite",
            "block": "A",
            "lot_number": "01"
        },
        "transaction_date": "2025-06-21T10:35:00+07:00",
        "billing_periods": "April 2025, Mei 2025",
        "billing_count": 2,
        "total_billing": 700000.00,
        "total_penalty": 21000.00,
        "grand_total": 721000.00,
        "payment_method": { "id": "C", "name": "Cash" },
        "cashier_name": "Rini Pertiwi",
        "loket_code": "L01",
        "pdf_url": "/api/v1/documents/spt/GD.2025.06.000123"
    }
}
```

---

### 18.6 INSTALLMENT — Cicilan

---

#### `POST /installments`

**Deskripsi:** Input cicilan pembayaran. Cicilan dicatat sebagai kredit pelanggan dan dialokasikan secara manual oleh Back Office.  
**Akses:** root, back_office, loket

**Request Body:**
```json
{
    "customer_id": "DO001",
    "amount": 150000,
    "payment_date": "2025-06-15",
    "notes": "Cicilan bulan Juni, tunggakan April-Mei"
}
```

**Validasi:**
- `customer_id`: wajib, pelanggan harus aktif
- `amount`: wajib, numeric, min 1000
- `payment_date`: wajib, format Y-m-d, tidak boleh future date

**Response 201:**
```json
{
    "success": true,
    "message": "Cicilan berhasil dicatat.",
    "data": {
        "id": 501,
        "customer_id": "DO001",
        "customer_name": "Budi Santoso",
        "amount": 150000.00,
        "payment_date": "2025-06-15",
        "notes": "Cicilan bulan Juni, tunggakan April-Mei",
        "created_by": "Rini Pertiwi",
        "created_at": "2025-06-15T09:00:00+07:00"
    }
}
```

---

#### `GET /customers/{id}/installments`

**Deskripsi:** Riwayat cicilan satu pelanggan.  
**Akses:** root, back_office, loket

**Response 200:**
```json
{
    "success": true,
    "data": {
        "customer": {
            "id": "DO001",
            "name": "Budi Santoso"
        },
        "installments": [
            {
                "id": 501,
                "amount": 150000.00,
                "payment_date": "2025-06-15",
                "notes": "Cicilan bulan Juni",
                "allocated_to": null
            }
        ],
        "summary": {
            "total_installments": 1,
            "total_amount": 150000.00
        }
    }
}
```

---

### 18.7 BACK PAYMENT — Pelunasan Mundur

---

#### `POST /back-payments/process`

**Deskripsi:** Proses pelunasan tagihan untuk periode lampau yang masih outstanding.  
**Akses:** root, back_office

**Request Body:**
```json
{
    "customer_id": "DO001",
    "billing_id": 999,
    "payment_method_id": "C",
    "cashier_name": "Brata Kusuma",
    "notes": "Pelunasan mundur tagihan Maret 2023"
}
```

**Validasi:**
- `billing_id` harus status `01` (belum bayar)
- `billing_id` harus milik `customer_id`
- `billing_id` harus sudah di-approve

**Response 201:**
```json
{
    "success": true,
    "message": "Pelunasan mundur berhasil diproses.",
    "data": {
        "receipt_number": "GD.2025.06.000124",
        "billing_period": "Maret 2023",
        "amount": 320000.00,
        "penalty": 9600.00,
        "grand_total": 329600.00,
        "pdf_url": "/api/v1/documents/spt/GD.2025.06.000124"
    }
}
```

---

### 18.8 REVERSAL — Pembatalan Transaksi

---

#### `POST /reversals`

**Deskripsi:** Ajukan pembatalan transaksi (kuitansi). Request ini tidak langsung membatalkan — perlu approval dari Back Office/Root.  
**Akses:** root, back_office, loket

**Request Body:**
```json
{
    "receipt_number": "GD.2025.06.000123",
    "reason": "Salah input — tagihan dibayar dobel oleh kasir"
}
```

**Validasi:**
- `receipt_number`: wajib, harus ada di tabel receipts
- `reason`: wajib, min 10 karakter
- Belum ada pembatalan pending untuk receipt yang sama

**Response 201:**
```json
{
    "success": true,
    "message": "Pengajuan pembatalan berhasil disubmit. Menunggu approval.",
    "data": {
        "id": 15,
        "receipt_number": "GD.2025.06.000123",
        "reason": "Salah input — tagihan dibayar dobel oleh kasir",
        "status": "pending",
        "submitted_by": "Rini Pertiwi",
        "submitted_at": "2026-06-02T11:00:00+07:00"
    }
}
```

---

#### `GET /reversals`

**Deskripsi:** Daftar pengajuan pembatalan.  
**Akses:** root, back_office

**Query Parameters:**
| Parameter | Deskripsi |
|-----------|-----------|
| `status` | `pending`, `approved`, `rejected` |
| `date_from` | Filter dari tanggal |
| `date_to` | Filter sampai tanggal |

**Response 200:**
```json
{
    "success": true,
    "data": [
        {
            "id": 15,
            "receipt_number": "GD.2025.06.000123",
            "customer": { "id": "DO001", "name": "Budi Santoso" },
            "grand_total": 721000.00,
            "reason": "Salah input",
            "status": "pending",
            "submitted_by": "Rini Pertiwi",
            "submitted_at": "2026-06-02T11:00:00+07:00"
        }
    ]
}
```

---

#### `POST /reversals/{id}/approve`

**Deskripsi:** Setujui pembatalan — tagihan di-rollback ke status belum bayar, kuitansi dihapus.  
**Akses:** root, back_office

**Request Body:**
```json
{
    "review_notes": "Dikonfirmasi, pembayaran ganda. Disetujui untuk dibatalkan."
}
```

**Response 200:**
```json
{
    "success": true,
    "message": "Pembatalan disetujui. Transaksi GD.2025.06.000123 telah dibatalkan.",
    "data": {
        "id": 15,
        "receipt_number": "GD.2025.06.000123",
        "status": "approved",
        "reviewed_by": "Brata Kusuma",
        "reviewed_at": "2026-06-02T12:00:00+07:00",
        "billings_reverted": [1001, 1002]
    }
}
```

---

#### `POST /reversals/{id}/reject`

**Deskripsi:** Tolak pengajuan pembatalan.  
**Akses:** root, back_office

**Request Body:**
```json
{
    "review_notes": "Pembayaran sudah dikonfirmasi valid oleh pelanggan."
}
```

**Response 200:**
```json
{
    "success": true,
    "message": "Pengajuan pembatalan ditolak.",
    "data": {
        "id": 15,
        "status": "rejected",
        "reviewed_by": "Brata Kusuma"
    }
}
```

---

### 18.9 RECEIVABLE — Piutang

---

#### `GET /receivables`

**Deskripsi:** Daftar piutang (tagihan belum lunas) per pelanggan/klaster.  
**Akses:** root, back_office

**Query Parameters:**
| Parameter | Deskripsi |
|-----------|-----------|
| `cluster_id` | Filter per klaster |
| `customer_id` | Filter per pelanggan |
| `year` | Filter tahun tagihan |
| `month` | Filter bulan tagihan |

**Response 200:**
```json
{
    "success": true,
    "data": [
        {
            "customer": {
                "id": "DO001",
                "name": "Budi Santoso",
                "cluster": "Dolomite",
                "block": "A",
                "lot_number": "01"
            },
            "outstanding_bills": [
                {
                    "id": 1001,
                    "period": "April 2025",
                    "amount": 350000.00,
                    "age_days": 60
                }
            ],
            "total_outstanding": 350000.00,
            "oldest_billing": "April 2025"
        }
    ],
    "meta": {
        "total_customers": 45,
        "total_outstanding": 15750000.00
    }
}
```

---

#### `GET /receivables/aging`

**Deskripsi:** Analisis umur piutang — kategorisasi outstanding berdasarkan usia tunggakan.  
**Akses:** root, back_office

**Response 200:**
```json
{
    "success": true,
    "data": {
        "summary": {
            "current": {
                "label": "< 30 hari (bulan berjalan)",
                "customer_count": 12,
                "total_amount": 4200000.00
            },
            "overdue_30": {
                "label": "30–60 hari (1 bulan tunggak)",
                "customer_count": 20,
                "total_amount": 7000000.00
            },
            "overdue_60": {
                "label": "60–90 hari (2 bulan tunggak)",
                "customer_count": 8,
                "total_amount": 2800000.00
            },
            "overdue_90": {
                "label": "> 90 hari (risiko tinggi)",
                "customer_count": 5,
                "total_amount": 1750000.00
            }
        },
        "total_outstanding": 15750000.00,
        "as_of_date": "2026-06-02"
    }
}
```

---

### 18.10 REPORT — Laporan Keuangan

---

#### `GET /reports/dashboard`

**Deskripsi:** Ringkasan KPI dashboard untuk semua role.  
**Akses:** Semua role

**Response 200:**
```json
{
    "success": true,
    "data": {
        "current_period": { "year": 2025, "month": 6, "label": "Juni 2025" },
        "billing_summary": {
            "total_bills": 248,
            "total_amount": 86800000.00,
            "paid_count": 180,
            "paid_amount": 63000000.00,
            "unpaid_count": 68,
            "unpaid_amount": 23800000.00,
            "collection_rate": 72.60
        },
        "today_transactions": {
            "count": 12,
            "total": 4200000.00
        },
        "pending_approvals": 15,
        "pending_reversals": 2
    }
}
```

---

#### `GET /reports/monthly`

**Deskripsi:** Laporan tagihan bulanan per klaster (agregasi).  
**Akses:** root, back_office

**Query Parameters:**
| Parameter | Wajib | Deskripsi |
|-----------|-------|-----------|
| `year` | ✓ | Tahun laporan |
| `month` | ✓ | Bulan laporan |

**Response 200:**
```json
{
    "success": true,
    "data": {
        "period": { "year": 2025, "month": 6, "label": "Juni 2025" },
        "clusters": [
            {
                "cluster_id": "AM",
                "cluster_name": "Amber",
                "total_count": 20,
                "total_amount": 6000000.00,
                "total_penalty": 0.00,
                "paid_count": 15,
                "paid_amount": 4500000.00,
                "unpaid_count": 5,
                "unpaid_amount": 1500000.00,
                "collection_rate": 75.00
            }
        ],
        "grand_total": {
            "total_count": 248,
            "total_amount": 86800000.00,
            "paid_amount": 63000000.00,
            "unpaid_amount": 23800000.00,
            "collection_rate": 72.60
        }
    }
}
```

---

#### `GET /reports/daily-receipt`

**Deskripsi:** LPP — Laporan Penerimaan Pembayaran harian.  
**Akses:** root, back_office

**Query Parameters:**
| Parameter | Wajib | Deskripsi |
|-----------|-------|-----------|
| `date` | ✓ | Tanggal (Y-m-d) |

**Response 200:**
```json
{
    "success": true,
    "data": {
        "date": "2025-06-21",
        "transactions": [
            {
                "receipt_number": "GD.2025.06.000120",
                "customer_name": "Budi Santoso",
                "cluster": "Dolomite",
                "grand_total": 721000.00,
                "cashier_name": "Rini",
                "payment_method": "Cash",
                "transaction_time": "09:15:00"
            }
        ],
        "summary": {
            "transaction_count": 12,
            "grand_total": 4200000.00,
            "by_cashier": [
                { "cashier": "Rini", "count": 7, "total": 2450000.00 },
                { "cashier": "Ayu", "count": 5, "total": 1750000.00 }
            ]
        }
    }
}
```

---

#### `GET /reports/reconciliation`

**Deskripsi:** RPP — Rekonsiliasi pembayaran (tagihan yang diharapkan vs. yang diterima).  
**Akses:** root, back_office

**Query Parameters:** `year`, `month`

**Response 200:**
```json
{
    "success": true,
    "data": {
        "period": "Juni 2025",
        "expected_billing": 86800000.00,
        "received_payment": 63000000.00,
        "outstanding_receivable": 23800000.00,
        "collection_rate": 72.60,
        "total_penalty_collected": 2100000.00
    }
}
```

---

#### `GET /reports/collector`

**Deskripsi:** Laporan performa kasir/kolektor per periode.  
**Akses:** root, back_office

**Query Parameters:** `year`, `month`

**Response 200:**
```json
{
    "success": true,
    "data": [
        {
            "cashier_name": "Rini Pertiwi",
            "transaction_count": 45,
            "total_collected": 16450000.00
        },
        {
            "cashier_name": "Ayu Lestari",
            "transaction_count": 38,
            "total_collected": 13860000.00
        }
    ],
    "meta": {
        "period": "Juni 2025",
        "total_transactions": 83,
        "total_collected": 30310000.00
    }
}
```

---

### 18.11 DOCUMENT — Cetak PDF

---

#### `GET /documents/spt/{receiptNumber}`

**Deskripsi:** Generate dan download PDF Surat Pembayaran Tunai (SPT) / kuitansi. Setiap request increment `spt_print_count` pada billing terkait.  
**Akses:** root, back_office, loket  
**Path Parameter:** `receiptNumber` — Nomor kuitansi

**Response:** `application/pdf` (download file)

**Response Headers:**
```http
Content-Type: application/pdf
Content-Disposition: attachment; filename="SPT-GD.2025.06.000123.pdf"
```

**Response 404 — Kuitansi tidak ditemukan:**
```json
{
    "success": false,
    "message": "Kuitansi tidak ditemukan.",
    "error_code": "ERR_NOT_FOUND"
}
```

---

#### `GET /documents/spk/{billingId}`

**Deskripsi:** Generate PDF Surat Pemberitahuan Kredit (SPK) — invoice sebelum pembayaran.  
**Akses:** root, back_office, loket  
**Path Parameter:** `billingId` — Billing ID

**Query Parameters:**
| Parameter | Deskripsi |
|-----------|-----------|
| `inline` | `true` = tampil di browser, `false` = download (default) |

**Response:** `application/pdf`

---

#### `GET /documents/billing-recap`

**Deskripsi:** Generate PDF rekap tagihan per periode.  
**Akses:** root, back_office

**Query Parameters:**
| Parameter | Deskripsi |
|-----------|-----------|
| `year` | Tahun |
| `month` | Bulan |
| `cluster_id` | Filter klaster (opsional) |
| `property_type_id` | Filter tipe properti (opsional) |

**Response:** `application/pdf`

---

#### `GET /documents/customer-list`

**Deskripsi:** Generate PDF daftar master pelanggan.  
**Akses:** root, back_office

**Query Parameters:** `cluster_id`, `status_id`, `property_type_id`

**Response:** `application/pdf`

---

#### `GET /documents/cluster-recap`

**Deskripsi:** Generate PDF rekap per klaster.  
**Akses:** root, back_office

**Query Parameters:** `year`, `month`

**Response:** `application/pdf`

---

### 18.12 USER MANAGEMENT

---

#### `GET /users`

**Deskripsi:** Daftar semua user sistem.  
**Akses:** root only

**Response 200:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Brata Kusuma",
            "username": "brata",
            "email": "brata@grandduta.com",
            "role": "root",
            "is_active": true,
            "last_login_at": "2026-06-02T08:00:00+07:00"
        }
    ]
}
```

---

#### `POST /users`

**Deskripsi:** Tambah user baru.  
**Akses:** root only

**Request Body:**
```json
{
    "name": "Siti Aminah",
    "username": "siti",
    "email": "siti@grandduta.com",
    "password": "TemporaryPass123!",
    "password_confirmation": "TemporaryPass123!",
    "role": "loket"
}
```

**Validasi:**
- `username`: wajib, unik, 3–50 karakter, alphanum + underscore
- `email`: opsional, format email valid, unik
- `password`: wajib, min 8 karakter, harus ada huruf besar + angka
- `role`: wajib, salah satu dari: `root`, `back_office`, `loket`, `cs`

**Response 201:**
```json
{
    "success": true,
    "message": "User berhasil dibuat.",
    "data": {
        "id": 8,
        "name": "Siti Aminah",
        "username": "siti",
        "role": "loket",
        "is_active": true
    }
}
```

---

#### `POST /users/{id}/reset-password`

**Deskripsi:** Reset password user ke password temporary.  
**Akses:** root only

**Request Body:**
```json
{
    "new_password": "TempPass456!",
    "new_password_confirmation": "TempPass456!"
}
```

**Response 200:**
```json
{
    "success": true,
    "message": "Password user Siti Aminah berhasil direset."
}
```

---

#### `POST /users/{id}/toggle-status`

**Deskripsi:** Aktifkan / nonaktifkan user (tanpa hapus, untuk audit trail).  
**Akses:** root only

**Response 200:**
```json
{
    "success": true,
    "message": "User Siti Aminah berhasil dinonaktifkan.",
    "data": {
        "id": 8,
        "is_active": false
    }
}
```

---

### 18.13 LOOKUP — Data Referensi

---

#### `GET /lookup/regencies`

**Deskripsi:** Daftar kabupaten/kota.  
**Akses:** root, back_office (saat input pelanggan)

**Response 200:**
```json
{
    "success": true,
    "data": [
        { "id": "BDL", "name": "Kota Bandar Lampung" },
        { "id": "LPS", "name": "Lampung Selatan" }
    ]
}
```

---

#### `GET /lookup/districts`

**Query Parameters:** `regency_id` (wajib)

**Response 200:**
```json
{
    "success": true,
    "data": [
        { "id": "BDL01", "name": "Kedaton", "regency_id": "BDL" },
        { "id": "BDL02", "name": "Rajabasa", "regency_id": "BDL" }
    ]
}
```

---

### 18.14 AUDIT LOG

---

#### `GET /audit-logs`

**Deskripsi:** Daftar audit log aktivitas sistem.  
**Akses:** root only

**Query Parameters:**
| Parameter | Deskripsi |
|-----------|-----------|
| `user_id` | Filter per user |
| `module` | Filter per modul |
| `action` | Filter per aksi (CREATE, UPDATE, DELETE) |
| `date_from` | Filter dari tanggal |
| `date_to` | Filter sampai tanggal |

**Response 200:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1234,
            "user": { "id": 1, "name": "Brata Kusuma" },
            "action": "PAYMENT",
            "module": "payments",
            "record_id": "GD.2025.06.000123",
            "old_data": null,
            "new_data": { "grand_total": 721000 },
            "ip_address": "192.168.1.10",
            "created_at": "2025-06-21T10:35:00+07:00"
        }
    ],
    "meta": { "total": 5430 }
}
```

---

*Lanjutan di TECHNICAL_SPEC_PART4.md*
