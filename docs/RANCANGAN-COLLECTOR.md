# Rancangan Modul Collector — Web & Android

> Status: **Draft analisis & rancangan** (8 Okt 2026). Belum ada perubahan kode.
> Cakupan: backend Laravel 12 (`app/`), web React 19 + antd 6 (`frontend/`), aplikasi Flutter (`android/app/`).
> Prinsip utama: **memperluas yang sudah ada, bukan membangun ulang.** Sekitar 40% fondasi collector sudah berjalan.

---

## 1. Analisis Proyek Saat Ini

### 1.1 Arsitektur yang ada

| Lapisan | Teknologi | Catatan penting |
|---|---|---|
| Backend | Laravel 12, Sanctum, Spatie Permission v6, DomPDF | Satu API `/api/v1` untuk web & mobile. Envelope `ApiResponse` (`{success,message,data,meta}`), pagination `meta.current_page/per_page/total/last_page`. Pesan berbahasa Indonesia. |
| Web | React 19, antd 6, react-query 5, react-router 7, recharts, leaflet | Menu statis di `frontend/src/constants/permissions.js`, route di `routes/AppRoutes.jsx` dengan `<Protected permissions roles>`. Dark mode **sudah ada** (`ThemeContext`). Bahasa UI Indonesia, tanpa i18n. |
| Mobile | **Flutter** (bukan native Kotlin), Material 3, `package:http` | Shell per role (customer / collector / supervisor) dengan floating bottom nav. Tanpa state management/DI/router; JSON tak bertipe. **Tanpa DB lokal, tanpa FCM.** |
| Test | PHPUnit + sqlite `:memory:` | 38 feature test, termasuk `CollectorScopingTest`, `CollectorVisitSignatureTest`, `ManualPaymentProofTest`, `PaymentFlowTest`. |

### 1.2 Hierarki domain (sudah ada — dipakai ulang)

```
clusters (id char2)
  └─ units (id char5; block = kolom string, BUKAN tabel; lat/lng; va_number; balance)
       ├─ residents (pemilik)  ← units.resident_id
       ├─ residents (penyewa)  ← units.tenant_resident_id, billing_payer
       ├─ unit_occupants (family/staff, phone)
       └─ billings (unit_id, year, month; amount, principal_paid, penalty, penalty_paid,
                    penalty_waived_amount, discount; status 01 Belum Bayar / 02 Lunas /
                    03 Sebagian / 04 Dibatalkan)
             ├─ payment_allocations (principal/penalty per transaksi)
             ├─ billing_payment_transaction (pivot) ── payment_transactions
             └─ receipts
```

- **Jatuh tempo** = tanggal `grandduta.notification.penalty_day` (default 20) pada bulan tagihan — `PenaltyService::dueDate()`. Status invoice: `PenaltyService::invoiceStatus()`.
- **Partial payment sudah didukung** (status `03 Sebagian`, alokasi FIFO di `PaymentService`).

### 1.3 Modul collector/supervisor yang sudah ada

| Fitur | Backend | Web | Flutter |
|---|---|---|---|
| Profil collector (kode, WA, status kerja, jam dinas) | `collector_profiles` | `CollectorsPage`, `CollectorDetailPage` | profil |
| Assignment (scope cluster/block/unit/resident, reassign → `transferred`, priority, periode) | `collector_assignments` + `CollectorAssignmentService` (resolver tunggal, `assertUnitAssigned`) | `CollectorAssignmentsPage` | — |
| Target (daily/weekly/monthly, amount + visit count) | `collector_targets` | `CollectorTargetsPage` | — |
| Performance (collected vs target, visit) | `CollectorPerformanceService` | `CollectorPerformancePage` (statistic saja) | dashboard + performance |
| Visit (purpose, result, met_with, GPS check-in, status, next_visit_date) | `collector_visits` | tab di `ResidentDetailPage` | `visit_form_screen` (GPS + foto + tanda tangan) |
| Bukti visit (photo/document/gps/signature) | `collector_visit_evidence` | API ada, **UI belum** | ada |
| Promise to Pay (amount, date, method, follow_up_date; pending/fulfilled/broken/rescheduled) | `payment_promises` | tab di `ResidentDetailPage` | `ptp_form_screen` |
| Pembayaran lapangan | `PaymentService::process` → langsung **paid** | — | `payment_collection_screen` (preview + process) |
| Verifikasi bukti transfer manual (verify/reject) | `PaymentGatewayController::verifyManual/rejectManual` | `PaymentsPage` | — |
| Pengingat WA (log link wa.me) | `collector_reminders` | `CollectorRemindersPage` | ada |
| Surat penagihan (PDF) | `collection_letters` | `CollectionLettersPage` | ada |
| Lokasi collector (ping 5 menit di foreground, dibatasi jam dinas) | `collector_locations` | `CollectorLiveMapPage` | shell timer |
| Supervisor: dashboard, monitoring collector, tunggakan, map, approvals, notifikasi + eskalasi notifikasi, export | `supervisor/*`, `supervisor_notifications`, `SupervisorNotificationGeneratorService` (cron per jam) | `pages/supervisor/*` | `screens/supervisor/*` |
| Audit | `AuditService::log` + middleware `audit` di semua non-GET | `audit-logs` | — |
| Notifikasi in-app | `notification_queues` (+ `supervisor_notifications` terpisah) | `NotificationBell`, inbox | `notification_list_view` |

### 1.4 Role & permission yang ada

Role (seed `RolePermissionSeeder`): `root, super_admin, admin_estate, back_office, finance, property_manager, operations_staff, security, technician, vendor, loket, cs, collector, supervisor, customer`.

Pemetaan ke role pada brief:

| Role brief | Role existing | Tindakan |
|---|---|---|
| Super Admin | `root`, `super_admin` | pakai |
| Estate Manager | `property_manager` (+ `admin_estate`) | pakai, tambahkan permission `collection.*` view |
| Collection Supervisor | `supervisor` | pakai, scoping via `SupervisorAssignmentService` (per cluster) |
| Collector | `collector` | pakai, scoping via `CollectorAssignmentService` |
| Finance | `finance` (+ `loket`) | pakai, `payments.verify` sudah ada |

Permission collector sudah banyak (`visits.*`, `payment-promises.*`, `collector-*`, `collector-evidence.*`, `collector-complaints.*`, `payments.verify`, `approvals.*`). Yang perlu ditambah: `collection-accounts.view`, `collection-activities.*`, `collection-notes.*`, `collection-disputes.*`, `collection-escalations.*`, `collection-payments.submit`, `collection-sync.push`, `collection-reports.view`.

### 1.5 Gap analysis

| # | Kebutuhan brief | Kondisi | Gap |
|---|---|---|---|
| G1 | Collection Account (outstanding, aging, priority, status, last contact, next follow-up) | Tidak ada; collector melihat daftar `units` biasa | **Besar** — butuh service + endpoint `collector/accounts` |
| G2 | Aging bucket reusable | Logika inline di `ReceivableController::aging` (tier bulan) | Ekstrak ke `CollectionAgingService` (bucket hari: current/1-30/31-60/61-90/91-180/>180) |
| G3 | Priority scoring | Hanya skor per cluster di `SupervisorTunggakanController` + priority manual assignment | Service baru |
| G4 | Payment collector → **Pending Verification** | `PaymentService::process` langsung `paid` | **Perubahan perilaku** — lihat Keputusan D1 |
| G5 | Request Revision pada verifikasi | Hanya verify/reject | Tambah status `revision_requested` |
| G6 | Contact log (call/WA/SMS/email + result) | `collector_reminders` hanya mencatat link WA | Tabel `collection_activities` |
| G7 | Timeline terpadu | Tidak ada | Dibangun dari `collection_activities` |
| G8 | Dispute | Tidak ada (`resident_complaints` ≠ dispute tagihan) | Tabel baru |
| G9 | Escalation kasus penagihan | Hanya eskalasi `supervisor_notifications` | Tabel baru + workflow |
| G10 | Notes internal | Hanya kolom `notes` bebas | Tabel baru |
| G11 | Broken PTP otomatis | Tidak ada job; generator hanya membaca yang sudah `broken` | Job harian `collection:evaluate-promises` |
| G12 | PTP terhubung collector/visit, status `cancelled` | Tidak ada `collector_id`, `visit_id`, `cancelled` | Tambah kolom |
| G13 | Visit terjadwal (Today/Upcoming/Failed), start/finish time | Visit hanya dicatat setelah terjadi | Tambah `scheduled_*`, `started_at`, `finished_at`, lifecycle |
| G14 | Offline sync, idempotency, konflik | Tidak ada sama sekali (web/mobile/backend) | **Besar** |
| G15 | Push notification | Tidak ada FCM | `device_tokens` + FCM |
| G16 | Intent `tel:` / `geo:` | Tidak ada; maps via link https | Kecil |
| G17 | Export Excel | Hanya CSV (`fputcsv`) & PDF | Lihat Keputusan D4 |
| G18 | Web: dashboard collector, My Collection, detail + timeline, skeleton, KPI card, chart wrapper, table→card mobile | Tidak ada; collector bahkan bisa kena `/403` di `/` | **Besar** |
| G19 | Performance multi-metric + ranking | Statistic saja | Sedang |
| G20 | Single-session untuk staff | `single-session` hanya di grup resident | Opsional |

---

## 2. Arsitektur Collector yang Direkomendasikan

```
┌──────────────┐   ┌────────────────────┐
│ Web (React)  │   │ Flutter Collector  │──► SQLite lokal (drift) + Outbox
└──────┬───────┘   └─────────┬──────────┘
       │  REST /api/v1 (Sanctum, envelope ApiResponse yang sama)
┌──────▼─────────────────────▼───────────────────────────────┐
│ Controllers tipis                                          │
│  /collector/*      (fasad untuk role collector, scoped)    │
│  /collection/*     (supervisor/manager/finance)            │
│  endpoint lama tetap jalan (backward compatible)           │
├────────────────────────────────────────────────────────────┤
│ Service layer (semua kalkulasi bisnis di sini)             │
│  CollectionAccountService   CollectionAgingService         │
│  CollectionPriorityService  CollectionTimelineService      │
│  CollectionActivityService  CollectionVisitService         │
│  PromiseToPayService        CollectionPaymentService ─┐    │
│  CollectionDisputeService   CollectionEscalationService│   │
│  CollectionSyncService      CollectionDashboardService │   │
│  CollectionReportService                               │   │
│  REUSE: CollectorAssignmentService, SupervisorAssignment│   │
│         Service, PaymentService ◄──────────────────────┘   │
│         PenaltyService, AuditService, ApprovalService      │
├────────────────────────────────────────────────────────────┤
│ Jobs/Scheduler: evaluate-promises (harian), refresh-account│
│  -states (setelah event + per jam), generator notifikasi   │
├────────────────────────────────────────────────────────────┤
│ MySQL: tabel existing + tabel baru collection_*            │
└────────────────────────────────────────────────────────────┘
```

Prinsip:
1. **Unit = Collection Account.** Tidak ada duplikasi customer/unit/tagihan. Customer akun = `billing_payer` unit (pemilik atau penyewa).
2. Semua angka (outstanding, aging, collection rate, priority, achievement) dihitung **hanya di backend**. Web & Flutter hanya menampilkan → angka Web dan Android selalu sama.
3. Setiap aksi penting menulis **satu baris `collection_activities`** (untuk timeline) **dan** `AuditService::log` (untuk audit). Timeline ≠ audit log: timeline untuk operasional, audit untuk kepatuhan.
4. Endpoint lama (`units/{u}/visits`, `payment-promises`, `payments/process`, dll.) **tidak dihapus**; endpoint `/collector/*` baru memanggil service yang sama.

---

## 3. Keputusan yang Perlu Disetujui

| ID | Keputusan | Rekomendasi |
|---|---|---|
| **D1** | Pembayaran oleh collector saat ini langsung `paid` dan langsung melunasi tagihan. Brief mewajibkan *Pending Verification*. | Payment dari role collector dibuat sebagai `payment_transactions` `payment_provider='collector'`, `status='waiting_verification'`, **tanpa** menyentuh `billings` sampai Finance menyetujui. Settlement memakai `PaymentService::settleGatewayTransaction` yang sudah ada. Loket **tidak berubah**. Bila perlu, flag konfigurasi `collector.payment_requires_verification` (default `true`) agar bisa rollback. |
| **D2** | Offline-first di Flutter butuh fondasi baru (DB lokal, model bertipe, state management). | Tambah `drift` (SQLite), `riverpod`, `go_router`, `connectivity_plus`, `workmanager` — **hanya untuk modul collector dulu**; layar customer/supervisor tidak disentuh. |
| **D3** | Push notification butuh project Firebase + `google-services.json`. | Siapkan backend (`device_tokens`, channel `push`) sekarang; aktifkan FCM setelah project Firebase tersedia. Sementara pakai polling notifikasi. |
| **D4** | Export Excel belum ada library. | Tambah `maatwebsite/excel` (atau `openspout/openspout` yang lebih ringan) untuk `.xlsx`; PDF tetap DomPDF. |
| **D5** | Kolom `customer` akun: pemilik atau penyewa? | Ikuti `units.billing_payer` (sudah dipakai billing). |
| **D6** | Visit `completed` saat ini wajib tanda tangan. | Pertahankan untuk result `payment_collected`/`customer_met`; result gagal (not home, address not found) tidak butuh tanda tangan tapi wajib foto + GPS. |

---

## 4. User Flow

### 4.1 Collector (Web & Android)

```
LOGIN ─► DASHBOARD (target hari ini, KPI, Priority Accounts)
  └─► OPEN ACCOUNT ─► review outstanding + timeline
        ├─► CALL / WHATSAPP (intent native) ─► bottom sheet "Contact Result" (1 tap)
        ├─► VISIT: Start (timestamp+GPS) ─► Result ─► Foto ─► Notes ─► Submit (Completed hh:mm)
        └─► Hasil:
              ├─ BAYAR ─► Pilih invoice ─► Nominal (numeric) ─► Metode ─► Foto bukti
              │           ─► Review/Konfirmasi ─► PENDING VERIFICATION
              │           ─► Finance: Approve ─► PAID | Reject (alasan) | Request Revision ─► collector revisi
              └─ TIDAK BAYAR ─► PROMISE TO PAY ─► follow-up (reminder H-1/H)
                          ─► FULFILLED (otomatis jika pembayaran terverifikasi ≥ janji)
                          └► BROKEN (job harian) ─► monitoring ─► ESCALATION (manual/otomatis)
```

### 4.2 Supervisor

```
Dashboard monitoring ─► Collector Performance / Ranking
  ├─► Assignment: manual │ bulk │ area (cluster/block) │ reassign (wajib alasan → audit)
  ├─► Target: set daily/weekly/monthly per collector
  ├─► Monitoring: Visit │ PTP (due today/tomorrow/broken) │ Dispute │ Escalation
  └─► Escalation inbox: terima ─► tangani / teruskan ke Estate Manager ─► Legal ─► resolve
```

### 4.3 Finance

```
Pending Verification queue ─► buka bukti ─► Approve | Reject (wajib alasan) | Request Revision (wajib catatan)
```

### 4.4 Offline (Android)

```
Aksi offline ─► simpan ke tabel lokal + outbox (client_uuid) ─► "3 item menunggu sinkron"
  ─► koneksi kembali / WorkManager ─► POST /collector/sync (batch, idempoten)
  ─► per item: applied │ duplicate (sudah ada) │ conflict │ rejected (validasi)
  ─► conflict ─► layar Sync Center: [Refresh] [Review Changes]
```

---

## 5. Information Architecture

### 5.1 Web — menu Collector (role `collector`)

```
Collector
├── Dashboard                         /collector
├── My Collection                     /collector/accounts?view=all|due_today|due_soon|overdue|critical|ptp
├── Visits                            /collector/visits?view=today|upcoming|completed|failed
├── Payments                          /collector/payments?status=submitted|waiting_verification|verified|rejected
├── Activities                        /collector/activities?type=call|whatsapp|visit|follow_up|note
├── Promise to Pay                    /collector/promises
├── Disputes                          /collector/disputes
├── Escalations                       /collector/escalations
├── Reports                           /collector/reports
└── (sudah ada) Rute, Pengingat WA, Surat Penagihan
```
Sub-menu di brief (Due Today, Overdue, dll.) diimplementasikan sebagai **tab/segmented filter dalam satu halaman** (satu URL dengan `view=`), bukan halaman terpisah, agar tidak ada duplikasi komponen.

### 5.2 Web — Collector Management (supervisor/manager/admin)

```
Collector Management
├── Collectors           (sudah ada: CollectorsPage)
├── Assignment           (sudah ada: perluas → bulk & area, audit reassign)
├── Collection Monitoring  /collection/monitoring  (baru: tabel semua akun lintas collector)
├── Collection Target    (sudah ada: perluas → target akun & collection rate)
├── Performance          (sudah ada: perluas → chart + ranking multi-metric)
├── Visit Monitoring     /collection/visits
├── Promise Monitoring   /collection/promises
├── Dispute Monitoring   /collection/disputes
├── Escalation Monitoring /collection/escalations
└── Reports              /collection/reports
```

Finance: menu **Payments → Verifikasi Collector** (`/collection/payments/verification`) di samping `PaymentsPage` yang ada.

### 5.3 Android — bottom navigation collector

Menggantikan tab lama (Beranda / Unit Saya / Rute / Notifikasi / Profil):

```
Home │ Collection │ Visits │ Activity │ Profile
```
Notifikasi pindah ke ikon lonceng di app bar; Rute & Sync Center masuk dari Home/Profile.

---

## 6. Database Design

### 6.1 Pemetaan entity brief → tabel

| Entity brief | Keputusan | Tabel |
|---|---|---|
| collectors | **Reuse** | `users` (role collector) + `collector_profiles` |
| collector_assignments | **Reuse + audit reassign** | `collector_assignments` (+ `reassigned_from_id`, `reason`) |
| collection_accounts | **Tidak dibuat sebagai master**; unit = akun. Dibuat **tabel cache turunan** untuk sort/filter cepat | `collection_account_states` (baru) |
| collection_activities | Baru | `collection_activities` |
| collection_visits | **Reuse + extend** | `collector_visits` |
| collection_visit_photos | **Reuse + extend** | `collector_visit_evidence` |
| collection_promises | **Reuse + extend** | `payment_promises` |
| collection_payments | **Reuse** | `payment_transactions` (`payment_provider='collector'`) + pivot billing |
| collection_payment_proofs | **Reuse** | `managed_files` (entity_type `payment_transaction`) |
| collection_disputes | Baru | `collection_disputes` |
| collection_escalations | Baru | `collection_escalations` (+ `collection_escalation_steps`) |
| collection_notes | Baru | `collection_notes` |
| collection_targets | **Reuse + extend** | `collector_targets` |
| collection_notifications | **Reuse** | `notification_queues` (+ `device_tokens` baru untuk push) |
| collection_sync_logs | Baru | `collection_sync_logs` |
| collection_audit_logs | **Reuse** | `audit_logs` |

### 6.2 Tabel baru

Semua tabel baru: `id` bigint, `created_by`, `updated_by` (FK users, nullable), `timestamps`. Tabel yang bisa dibuat offline juga punya `client_uuid` (uuid, unique, nullable) dan `version` (int, untuk deteksi konflik).

**`collection_account_states`** — cache turunan, *selalu bisa dibangun ulang* dari billings/aktivitas (bukan sumber kebenaran).
```
unit_id (PK, FK units, char5)
customer_resident_id (FK residents, nullable)   -- billing_payer saat refresh
outstanding_principal, outstanding_penalty, outstanding_total  decimal(15,2)
open_invoice_count int
oldest_due_date date null
aging_days int, aging_bucket enum(current,1_30,31_60,61_90,91_180,180_plus)
status enum(current,due_soon,due_today,overdue,partially_paid,promise_to_pay,disputed,escalated,paid)
priority_score smallint, priority_level enum(critical,high,medium,normal)
last_contact_at, last_contact_result, next_follow_up_at  nullable
failed_contact_count, failed_visit_count, broken_ptp_count  int
active_promise_id, active_dispute_id, active_escalation_id  (FK nullable)
collector_id (FK users, nullable)   -- hasil resolve assignment, untuk filter cepat
refreshed_at timestamp
INDEX (collector_id, priority_level), (status), (aging_bucket), (next_follow_up_at)
```
Di-refresh oleh `CollectionAccountService::refresh($unitId)` setiap event (pembayaran, PTP, visit, aktivitas, assignment) + job per jam sebagai jaring pengaman. Alasan cache: daftar "My Collection" harus bisa di-sort/filter berdasarkan priority/aging dengan pagination tanpa menghitung ulang penalti ribuan tagihan per request.

**`collection_activities`** — sumber timeline.
```
unit_id FK, customer_resident_id FK null, collector_id FK users null
type enum(call,whatsapp,sms,email,visit,promise,payment,dispute,escalation,note,assignment,letter,system)
channel_result enum(answered,no_answer,busy,wrong_number,callback_requested,promised_payment,other) null
subject_type, subject_id (morph ke visit/promise/payment/dispute/escalation/note) null
summary varchar(255), details json null
occurred_at datetime, latitude, longitude null
client_uuid unique null
INDEX (unit_id, occurred_at DESC), (collector_id, occurred_at), (type)
```

**`collection_notes`**
```
unit_id FK, title, body text, priority enum(low,normal,high), visibility enum(internal) default internal
client_uuid unique null, version, soft deletes (hanya supervisor+)
```
Attachment via `managed_files` (entity_type `collection_note`). Tidak pernah diekspos ke endpoint resident.

**`collection_disputes`**
```
dispute_number unique, unit_id FK, customer_resident_id FK null, billing_id FK null
type enum(already_paid,wrong_amount,wrong_customer,wrong_billing_period,duplicate_bill,service_issue,other)
description text, priority enum(low,normal,high,urgent)
status enum(open,under_review,resolved,rejected)
assigned_to FK users null, resolution_notes text null, resolved_by, resolved_at
client_uuid unique null, version
```
Dispute **tidak** mengubah/menghapus billing (Rule 8). Akun bertanda `disputed` → priority diturunkan dan tidak memicu eskalasi otomatis.

**`collection_escalations`** + **`collection_escalation_steps`**
```
escalations: escalation_number, unit_id FK, billing_id null, dispute_id null
  trigger enum(overdue_90,repeated_broken_ptp,refused,unreachable,large_outstanding,dispute_unresolved,manual)
  level enum(supervisor,estate_manager,legal), status enum(open,in_progress,resolved,closed)
  current_assignee_id FK users, reason text, outstanding_snapshot decimal(15,2)
  resolution_notes, resolved_by, resolved_at, client_uuid, version
steps: escalation_id FK, from_level, to_level, from_user_id, to_user_id, action enum(created,forwarded,commented,resolved), notes, created_at
```
`outstanding_snapshot` hanya catatan; eskalasi tidak mengubah outstanding (Rule 9).

**`collection_sync_logs`**
```
collector_id FK, device_id varchar, client_uuid unique, entity_type, operation enum(create,update)
payload_hash, result enum(applied,duplicate,conflict,rejected), server_entity_id null, message null
client_created_at, received_at
```

**`device_tokens`** — `user_id, platform, token unique, last_seen_at` (untuk FCM).

### 6.3 Perubahan tabel existing (aditif, aman, semua nullable/berdefault)

| Tabel | Kolom tambahan |
|---|---|
| `collector_visits` | `scheduled_date`, `scheduled_time`, `priority`, `started_at`, `finished_at`, `start_latitude/longitude`, `result_code` enum(customer_met, not_home, refused, address_not_found, promised_payment, payment_collected, reschedule, other), `lifecycle` enum(scheduled, in_progress, completed, failed, cancelled) default `completed` (data lama tetap valid), `client_uuid`, `version`. Kolom `status` lama dipertahankan & diisi dari `result_code` untuk kompatibilitas. |
| `collector_visit_evidence` | tambah nilai type: `selfie`, `property`, `payment_proof` |
| `payment_promises` | `collector_id`, `visit_id`, `fulfilled_amount`, `fulfilled_at`, `broken_at`, `cancelled_at`, `cancel_reason`, `client_uuid`, `version`; status tambah `cancelled` |
| `payment_transactions` | `collected_by` (FK users), `collected_at`, `collection_latitude/longitude`, `revision_notes`, `revision_requested_at`, `rejection_reason`, `client_uuid`, `version`; status tambah `revision_requested`; `payment_provider` tambah `collector` |
| `collector_assignments` | `reassigned_from_id` (FK self), `reassign_reason` |
| `collector_targets` | `target_account_count`, `target_collection_rate` |

Semua migrasi **hanya `add column` / `create table`**, tanpa drop/rename, sehingga aman terhadap data produksi. (Catatan: MySQL enum yang diperluas memakai `->change()` — diuji di sqlite terlebih dahulu; alternatif yang lebih aman adalah `string` + validasi di aplikasi, dan itu yang direkomendasikan untuk kolom status baru.)

### 6.4 ERD ringkas

```
clusters 1─* units 1─* billings *─* payment_transactions 1─* managed_files(proof)
               │  1─1 collection_account_states
               │  1─* collector_visits 1─* collector_visit_evidence
               │  1─* payment_promises ─(visit_id)→ collector_visits
               │  1─* collection_activities ─(morph)→ visit|promise|payment|dispute|escalation|note
               │  1─* collection_notes
               │  1─* collection_disputes ─→ billings
               │  1─* collection_escalations 1─* collection_escalation_steps
users(collector) 1─1 collector_profiles, 1─* collector_assignments, 1─* collector_targets
users 1─* collection_sync_logs, 1─* device_tokens
```

---

## 7. Kalkulasi Bisnis (backend)

**Status akun** (urutan prioritas, yang pertama cocok dipakai — `CollectionAccountService::resolveStatus`):
1. `paid` — outstanding = 0 (tidak ada yang perlu ditagih, apa pun status PTP/dispute)
2. `escalated` — ada eskalasi aktif *(diaktifkan di T10)*
3. `disputed` — ada dispute open/under_review *(diaktifkan di T10)*
4. `promise_to_pay` — ada PTP `pending` dengan tanggal janji ≥ hari ini
5. `overdue` — ada invoice lewat jatuh tempo (`PenaltyService::dueDate`)
6. `partially_paid` — ada invoice status `03 Sebagian`, belum lewat jatuh tempo
7. `due_today` — jatuh tempo hari ini
8. `due_soon` — jatuh tempo ≤ `collector.due_soon_days` (default 7)
9. `current`

Outstanding hanya mencakup tagihan **disetujui** dan berstatus `01`/`03` — sama dengan yang bisa dibayar di `PaymentService`.

**Aging** = hari sejak `oldest_due_date` invoice yang belum lunas → bucket `current / 1–30 / 31–60 / 61–90 / 91–180 / >180`.

**Priority score** (0–100, bobot di `config/collector.php` agar tidak hardcode):
```
aging        : min(aging_days / 180, 1) × 35
outstanding  : min(outstanding / large_outstanding_threshold, 1) × 25
broken PTP   : min(broken_ptp_count, 3) / 3 × 20
failed contact: min(failed_contact_count_30d, 5) / 5 × 10
failed visit : min(failed_visit_count_30d, 3) / 3 × 10
level: ≥70 critical, ≥45 high, ≥20 medium, else normal (disputed → maks medium)
```

**Collection rate** periode P = Σ pembayaran *terverifikasi* di P atas akun yang di-assign ÷ (Σ outstanding awal periode + tagihan terbit di P) × 100.

**Collected amount** collector = Σ `payment_transactions` status `paid` dengan `collected_by` = collector **atau** (`payment_provider='loket'` dan `created_by` = collector, untuk data lama). Memperbaiki keterbatasan `CollectorPerformanceService` saat ini.

---

## 8. API Specification

Base: `/api/v1`, `auth:sanctum` + `audit`. Envelope existing. Pagination: `?page=&per_page=` (default 15, maks 100), meta `{current_page, per_page, total, last_page}` — **dipertahankan sesuai konvensi existing** (bukan `page`) agar web & Flutter yang ada tidak rusak.

### 8.1 Collector (`/collector`, role collector; data otomatis di-scope ke assignment)

| Method | Path | Keterangan |
|---|---|---|
| GET | `/collector/dashboard?period=daily\|weekly\|monthly&cluster_id&block` | KPI, target vs actual, aging, priority breakdown, today visits, PTP due |
| GET | `/collector/accounts` | filter: `view, cluster_id, block, unit_id, customer, address, status[], aging_bucket[], priority[], min_outstanding, max_outstanding, ptp_status, due_from, due_to, search, sort` |
| GET | `/collector/accounts/{unit}` | header, customer info, outstanding summary, quick-action flags |
| GET | `/collector/accounts/{unit}/bills` | invoice list (dari `PenaltyService::calculateInvoiceTotal`) |
| GET | `/collector/accounts/{unit}/activities?type&cursor` | timeline (cursor pagination) |
| POST | `/collector/activities` | log contact: `{client_uuid, unit_id, type, channel_result, summary, next_follow_up_at}` |
| GET/POST | `/collector/visits` | list (`view=today\|upcoming\|completed\|failed`) / jadwalkan |
| POST | `/collector/visits/{id}/start` | `{latitude, longitude, started_at}` |
| PUT | `/collector/visits/{id}` | submit hasil `{result_code, notes, next_follow_up_at, finished_at, version}` |
| POST | `/collector/visits/{id}/evidence` | multipart (reuse controller existing) |
| GET/POST | `/collector/promises` | `{client_uuid, unit_id, billing_id?, visit_id?, promised_amount, promised_date, payment_method, notes}` |
| POST | `/collector/promises/{id}/cancel` | `{reason}` |
| GET/POST | `/collector/payments` | submit: `{client_uuid, unit_id, billing_ids[], amount, payment_method, notes, collected_at, latitude?, longitude?}` → `waiting_verification` |
| POST | `/collector/payments/{id}/proof` | multipart `file` |
| PUT | `/collector/payments/{id}` | hanya saat `revision_requested` |
| GET/POST | `/collector/disputes` | |
| GET/POST | `/collector/escalations` | manual escalation |
| GET/POST | `/collector/notes` | |
| GET | `/collector/assignments` | assignment milik sendiri |
| GET | `/collector/performance?period` | (alias `collector-performance/me` lama tetap ada) |
| GET | `/collector/notifications` | reuse `notifications` |
| POST | `/collector/sync` | batch offline (lihat 8.4) |
| GET | `/collector/sync/bootstrap?since=` | delta data untuk cache offline (akun + tagihan terbuka + PTP aktif + visit terjadwal), dipaginasi |
| POST | `/devices` / DELETE `/devices/{token}` | registrasi FCM |

### 8.2 Management (`/collection`, supervisor/manager/admin, scoped per cluster supervisor)

| Method | Path |
|---|---|
| GET | `/collection/dashboard`, `/collection/accounts` (filter + `collector_id`) |
| POST | `/collection/assignments/bulk` `{collector_id, unit_ids[] \| cluster_id+block, start_date, priority, notes}` |
| POST | `/collector-assignments/{id}/reassign` (existing; tambah `reason` wajib) |
| GET | `/collection/visits`, `/collection/promises` (+summary: total, due_today, due_tomorrow, fulfilled, broken, success_rate) |
| GET/PATCH | `/collection/disputes/{id}` `{status, resolution_notes}` |
| GET | `/collection/escalations`; POST `/{id}/forward` `{to_level, to_user_id, notes}`; POST `/{id}/resolve` |
| GET | `/collection/performance?period&metric=amount\|rate\|visits\|ptp_fulfilled\|overdue_reduction` (ranking) |
| GET | `/collection/reports/{collection\|aging\|collector\|visit\|ptp\|payment}?format=json\|xlsx\|pdf` |

### 8.3 Finance

| Method | Path |
|---|---|
| GET | `/collection/payments?status=waiting_verification` |
| POST | `/collection/payments/{id}/approve` |
| POST | `/collection/payments/{id}/reject` `{reason}` (wajib) |
| POST | `/collection/payments/{id}/request-revision` `{notes}` (wajib) |

### 8.4 Sync payload

```json
POST /collector/sync
{
  "device_id": "a1b2…",
  "items": [
    { "client_uuid": "7f…", "entity": "activity", "op": "create",
      "client_created_at": "2026-10-08T10:31:00+07:00", "data": { "unit_id": "A0101", "type": "call", "channel_result": "no_answer" } },
    { "client_uuid": "8e…", "entity": "visit", "op": "update", "server_id": 812, "base_version": 3,
      "data": { "result_code": "not_home", "notes": "…" } }
  ]
}
→ 200
{ "success": true, "message": "Sinkronisasi selesai", "data": { "results": [
    { "client_uuid": "7f…", "result": "applied",   "server_id": 5521 },
    { "client_uuid": "8e…", "result": "conflict",  "server_version": 4, "server_data": { … } }
]}}
```
Aturan: `client_uuid` sama → `duplicate` (idempoten, aman diulang). `base_version` ≠ versi server → `conflict` (tidak pernah ditimpa). Payment offline hanya disimpan sebagai **draft lokal**; dikirim sebagai submit biasa setelah online + bukti terunggah.

### 8.5 Contoh response

```json
GET /collector/accounts/A0101
{
  "success": true,
  "message": "Akun penagihan berhasil diambil",
  "data": {
    "unit": { "id": "A0101", "cluster": {"id":"01","name":"Cluster 1"}, "block": "A", "lot_number": "01", "latitude": -6.2, "longitude": 106.8 },
    "customer": { "id": "R0000012", "name": "Budi Santoso", "phone": "0812…", "email": null, "role": "pemilik" },
    "outstanding": { "principal": 7500000, "penalty": 1000000, "admin_fee": 0, "other": 0, "total": 8500000 },
    "status": "overdue", "aging_days": 45, "aging_bucket": "31_60",
    "priority": { "level": "high", "score": 52 },
    "active_promise": null, "last_contact_at": "2026-10-07T10:30:00+07:00", "next_follow_up_at": "2026-10-09",
    "version": 7
  }
}
```

---

## 9. Permission Matrix

| Fitur | Super Admin | Estate Manager | Supervisor | Collector | Finance |
|---|---|---|---|---|---|
| Dashboard collection | ✓ | ✓ | ✓ (cluster sendiri) | ✓ (milik sendiri) | ✓ |
| Assignment | ✓ | ✓ | ✓ (cluster sendiri) | – (lihat milik sendiri) | – |
| Collection accounts | ✓ | ✓ | ✓ | Own | View |
| Contact activity & notes | ✓ | View | ✓ | Create (own) | – |
| Visit | ✓ | ✓ | ✓ | Own | View |
| Payment submit | ✓ | – | ✓ | Create | – |
| Payment verify/reject/revision | ✓ | – | – | **Tidak pernah** | ✓ |
| Ubah payment verified | Khusus (via reversal + approval existing) | – | – | – | – |
| PTP | ✓ | ✓ | ✓ | Create/cancel own | View |
| Dispute | ✓ | ✓ | Review/resolve | Create | View/resolve (already_paid) |
| Escalation | ✓ | Handle level manager | Handle level supervisor | Create | View |
| Target | ✓ | ✓ | ✓ | View own | – |
| Performance & ranking | ✓ | ✓ | ✓ | Own + posisi ranking | – |
| Reports | ✓ | ✓ | ✓ | Limited (own) | ✓ |
| Audit log | ✓ | View | View | – | View |
| Ubah/hapus invoice | sesuai modul billing existing | | | **Tidak pernah** | |

Penegakan: permission Spatie (route middleware) **dan** scoping di service (`CollectorAssignmentService::assertUnitAssigned`, `SupervisorAssignmentService::assertCollectorAssigned`) — tidak bergantung pada UI.

---

## 10. UI Screen Specification — Web

Komponen bersama baru (dibuat sekali, di `frontend/src/components/collection/` atau `common/`):
`StatCard` (KPI + delta + skeleton), `ChartCard` (recharts, warna dari token tema → aman dark mode), `AgingChart`, `TargetProgress`, `PriorityBadge`, `CollectionTimeline` (antd `Timeline`), `AccountCardList` (tampilan kartu untuk < md), `CollectorSelect`, `PageSkeleton` (dashboard/table/detail/timeline), `ConfirmAmountModal`.
Reuse: `PageHeader`, `FilterBar` + `UnitFilterFields` (Cluster/Block/Unit/Customer/Address — standar filter brief §63 sudah tersedia), `ResponsiveTable`, `StatusBadge` (tambah map `collectionAccount`, `visitLifecycle`, `ptp`, `collectionPayment`, `dispute`, `escalation`), `ExportPdfButton`, `MoneyInput`, `ErrorState`, `EmptyData`, `formatCurrency/formatDate`, `buildWhatsAppLink`.

| # | Screen | Purpose & layout | Komponen / field utama | Aksi & validasi | State & responsive | Permission |
|---|---|---|---|---|---|---|
| 1 | Collector Dashboard | Sapaan + tanggal; baris 8 StatCard; Target vs Actual (daily/weekly/monthly); Aging chart; Priority widget; daftar Priority Accounts & Today's Visits | filter tanggal, cluster, block (+collector untuk supervisor) | klik KPI → My Collection terfilter | skeleton per kartu; KPI 4→2→1 kolom | collector, supervisor+ |
| 2 | My Collection | Tabel akun + tab view | kolom brief §10; filter brief §11 | Call/WA/Open/aksi cepat via dropdown | desktop tabel, < md `AccountCardList`; empty "Tidak ada akun overdue — Great job!" | collector (own), supervisor+ (Collection Monitoring) |
| 3 | Collection Detail | Header (nama, unit, outstanding besar) + action bar; tab: Overview (customer info, outstanding summary), Bills, Timeline, Visits, PTP, Disputes, Notes | | Call, WA, Visit, Collect, PTP, Note, Dispute, Escalate (Drawer/Modal) | action bar menjadi sticky bottom di mobile | scoped |
| 4–6 | Visit List / Detail / Create | Tab today/upcoming/completed/failed; detail berisi bukti foto, peta titik GPS, durasi | form: akun (autocomplete), tanggal, jam, purpose (preset), priority, notes | result wajib saat selesai; foto wajib untuk hasil gagal | | |
| 7–9 | Payment List / Create / Detail | Status chip; Create: pilih invoice (checkbox, total otomatis) → nominal → metode → upload bukti → konfirmasi | | nominal > 0 dan ≤ outstanding terpilih (validasi ulang di server); bukti wajib untuk non-cash | detail menampilkan riwayat verifikasi | collector create; finance verify |
| 10–11 | PTP List / Detail | ringkasan pending/due today/fulfilled/broken; detail + aktivitas terkait | | tanggal ≥ hari ini, ≤ +30 hari (config); cancel wajib alasan | | |
| 12 | Activities | Feed/tabel aktivitas per tipe | filter tipe, tanggal, unit | | | |
| 13–16 | Disputes / Escalations (+detail) | list + detail dengan timeline step | | resolve/reject wajib catatan; forward wajib target level | | |
| 17 | Collector Performance | KPI multi-metric, chart tren, ranking dengan pilihan metric | | export | | supervisor+ |
| 18 | Assignment Management | perluasan halaman existing: mode Manual / Bulk (pilih banyak unit dari tabel) / Area; histori reassign | | reassign wajib alasan; cegah duplikasi scope aktif (sudah ada) | | supervisor+ |
| 19 | Collection Target | perluasan existing + target akun & rate; progress per collector | | | | supervisor+ |
| 20 | Reports | 6 tab report; filter standar; Export Excel/PDF | | | | per role |
| 21 | Notification Center | reuse `NotificationInbox` + tipe baru | | mark read, deep link ke entitas | | semua |
| — | Finance Verification Queue | tabel pending + preview bukti di Drawer | | Approve / Reject (alasan wajib) / Request Revision (catatan wajib) dengan konfirmasi | | finance |

Semua halaman: loading = skeleton, error = `ErrorState` + "Coba lagi", empty state berpesan, konfirmasi untuk aksi kritis (submit payment, reject, cancel PTP, reassign, escalate).

---

## 11. Android (Flutter) Specification

**Fondasi baru (khusus modul collector):** `drift` (SQLite), `flutter_riverpod`, `go_router` (deep link), `connectivity_plus`, `workmanager`, `uuid`, `firebase_messaging` + `flutter_local_notifications` (setelah D3), kompresi gambar (`flutter_image_compress`). Model bertipe + repository per fitur. Layar customer/supervisor tidak diubah.

| # | Screen | Inti |
|---|---|---|
| 1 | Login | existing (biometrik tetap) |
| 2 | Home | sapaan, Today's Target / Collected / progress, 4 kartu (Due Today, Overdue, Visits, PTP Due), Priority Accounts, indikator sinkron |
| 3 | Collection List | kartu akun (§37), search (customer/unit/phone/invoice), chip filter Overdue/Due Today/Priority/PTP, pull-to-refresh, data dari cache lokal |
| 4 | Collection Detail | header + quick action (Call, WhatsApp, Navigate, Collect, Promise, Note); tab Overview / Bills / Activity / Visit |
| 5 | Bill Detail | rincian pokok/denda/diskon/terbayar |
| 6 | Call/WA Action | intent → saat kembali ke app, bottom sheet Contact Result (1 tap + opsional follow-up date) |
| 7–10 | Visit List / Detail / Start / Result | Start → timestamp + GPS sekali ambil → result (preset chips) → foto (camera; selfie/properti/bukti) → notes → submit; "Visit Started 10:32" / "Visit Completed 10:48" |
| 11–13 | Payment / Proof / Confirmation | pilih invoice → nominal (numeric keyboard) → metode (dari API) → foto bukti → review → konfirmasi (§43) → Pending Verification |
| 14 | Promise to Pay | form 4 field (§44) |
| 15 | Activity | feed aktivitas milik collector |
| 16–17 | Dispute / Escalation | form singkat dengan preset type |
| 18 | Notifications | existing + routing ke entitas (perbaiki `notification_router` yang kini `null` untuk staff) |
| 19 | Sync Center | Last synced, antrian, item gagal/konflik dengan [Refresh] [Review Changes] |
| 20–21 | Profile / Settings | existing + tema, biometrik, status perangkat |

- **Offline:** cache akun yang di-assign, tagihan terbuka, PTP aktif, visit terjadwal (via `/collector/sync/bootstrap?since=`, dipaginasi; bukan seluruh DB). Aksi offline: activity, visit (termasuk foto disimpan lokal), notes, PTP, **payment draft**. Outbox diproses WorkManager saat online; badge "3 item menunggu sinkron".
- **Konflik:** server menolak dengan `conflict` + data server; aplikasi tidak menimpa — tampilkan perbandingan lokal vs server.
- **Kamera:** `image_picker` (existing) + kompresi + overlay timestamp/koordinat; foto disimpan lokal sampai terunggah.
- **GPS:** hanya on-demand (start/finish visit, submit payment). Ping lokasi periodik existing dipertahankan sesuai kebijakan jam dinas saat ini — **perlu konfirmasi** apakah tetap diinginkan mengingat brief meminta "jangan menyimpan lokasi terus-menerus".
- **Native action:** `tel:` (ACTION_DIAL), `https://wa.me/…`, `geo:lat,lng?q=` dengan fallback Google Maps https; tambah `<queries>` untuk tel/https/geo di `AndroidManifest.xml`, permission `CAMERA`, `POST_NOTIFICATIONS`.
- **Deep link:** `dutaresidence://collector/accounts/{unit}`, `/promises/{id}`, `/payments/{id}` — dipakai payload push.
- **UX:** satu tangan, target sentuh ≥ 48dp, aksi utama di bawah layar, form maksimal 4–5 field, bottom sheet untuk aksi cepat.

---

## 12. Business Rules

| # | Aturan | Penegakan |
|---|---|---|
| R1 | Collector hanya melihat assignment miliknya | Semua query `/collector/*` melewati `CollectorAssignmentService::unitIdsFor`; akses ke unit lain → 403 |
| R2 | Collector tidak dapat mengubah invoice | Tidak ada endpoint billing di grup collector; permission `billings.*` tidak dimiliki role collector |
| R3 | Collector tidak dapat memverifikasi payment | `approve/reject/request-revision` butuh `payments.verify`; tambahan cek `verified_by ≠ collected_by` |
| R4 | Partial payment diperbolehkan | Alokasi FIFO `PaymentService` existing |
| R5 | Payment verified tidak bisa diubah | Update hanya saat `revision_requested`; koreksi lewat Reversal + Approval existing |
| R6 | PTP bukan payment | PTP tidak menyentuh billing; fulfilled hanya saat payment terverifikasi |
| R7 | Broken PTP masuk monitoring | Job harian: `pending` & `promised_date < today` & pembayaran terverifikasi < janji → `broken`, activity + notifikasi supervisor |
| R8 | Dispute tidak menghapus invoice | Dispute hanya referensi `billing_id` |
| R9 | Escalation tidak mengubah outstanding | Hanya snapshot |
| R10 | Perubahan penting masuk audit | `AuditService::log` di setiap service + middleware `audit` existing; collector tidak punya akses hapus audit |
| R11 | Proses finansial dalam transaksi DB | `DB::transaction` + `lockForUpdate` pada billing saat settle (pola existing) |
| R12 | Idempotensi | `client_uuid` unik di semua entitas yang bisa dibuat offline |
| R13 | Auto-eskalasi (konfigurable) | overdue > 90 hari, broken PTP ≥ 2 dalam 90 hari, unreachable ≥ 5 kontak gagal, outstanding ≥ ambang, dispute > 14 hari — membuat eskalasi level supervisor; akun `disputed` dikecualikan |

---

## 13. Tahapan & Urutan Development

### 13.1 Prinsip urutan

1. **Backend dulu, lalu UI.** Setiap fitur dimulai dari migrasi → service → API → test, baru kemudian web dan Flutter. UI tidak pernah menunggu logika yang belum pasti.
2. **Vertical slice.** Setiap tahap menghasilkan sesuatu yang bisa dipakai end-to-end, bukan "semua migrasi dulu, semua UI belakangan".
3. **Risiko tinggi dikerjakan lebih awal.** Perubahan alur pembayaran (D1) dan fondasi offline Flutter (D2) dikerjakan cukup awal agar ada waktu UAT.
4. **Tidak merusak yang berjalan.** Setiap tahap ditutup dengan seluruh test existing (38 feature test) tetap hijau.
5. **Satu tahap = satu branch/PR**, dengan nama `feat/collector-tXX-<nama>`.

Ukuran: **S** ≈ 1–2 hari, **M** ≈ 3–5 hari, **L** ≈ 1–2 minggu (1 developer).

### 13.2 Peta urutan & jalur paralel

```
T0 Persiapan
 └─► T1 Fondasi data ─► T2 Service inti ─► T3 API baca collector
                                              │
             ┌────────────────────────────────┼─────────────────────────────┐
             ▼                                ▼                             ▼
   JALUR WEB                          JALUR BACKEND FITUR            JALUR FLUTTER
   T4 Komponen bersama web            T6 Contact activity & notes    T13 Fondasi Flutter
   T5 Halaman inti collector          T7 Payment + verifikasi (D1)   T14 Layar collector (online)
                                      T8 Visit                         (mengikuti T6–T10)
                                      T9 Promise to Pay
                                      T10 Dispute & Escalation
             └────────────────┬───────────────┘                            │
                              ▼                                            │
                    T11 Supervisor management                              │
                    T12 Reports & export                                   │
                              └──────────────────┬─────────────────────────┘
                                                 ▼
                                   T15 Offline sync ─► T16 Push notification
                                                 ▼
                                   T17 Hardening, UAT & rollout
```

Dengan dua developer: satu di jalur web + backend fitur, satu di jalur Flutter mulai dari T13 (setelah T3 selesai).

### 13.3 Milestone rilis

| Milestone | Setelah tahap | Yang bisa dipakai pengguna |
|---|---|---|
| **M1 – Visibility** | T5 | Collector bisa melihat dashboard, daftar akun prioritas, detail akun + timeline di web |
| **M2 – Field MVP** | T9 + T14 | Collector di lapangan (Android, online): kontak, visit, PTP, submit pembayaran; Finance memverifikasi |
| **M3 – Control** | T12 | Supervisor: dispute, eskalasi, bulk assignment, ranking, laporan + export |
| **M4 – Production-ready** | T17 | Offline, push notification, hardening |

---

### T0 — Persiapan · S
**Tergantung:** —
- [ ] Keputusan D1–D6 + kebijakan ping lokasi disetujui (§3).
- [x] Jalankan baseline test (dari HEAD bersih) sebelum perubahan.
- [ ] Siapkan database staging terpisah (bukan `.env` remote) untuk uji migrasi MySQL.
- [x] Perluas `config/collector.php`: `due_soon_days`, bobot priority, `large_outstanding_threshold`, ambang auto-eskalasi, `payment_requires_verification`, batas tanggal PTP.

**Selesai bila:** keputusan tercatat di dokumen ini, baseline test terdokumentasi.

### T1 — Fondasi data · M · ✅ selesai (8 Okt 2026)
**Tergantung:** T0
- [x] Migrasi tabel baru `2026_10_08_000001`–`000005`: `collection_account_states`, `collection_activities`, `collection_notes`, `collection_sync_logs`, `device_tokens` (dispute/escalation menyusul di T10 agar PR kecil).
- [x] Migrasi kolom aditif `000006`–`000009`: `payment_promises`, `collector_visits`, `payment_transactions`, `collector_assignments`, `collector_targets` (§6.3). Status baru disimpan sebagai `string` + validasi aplikasi, tanpa perubahan enum. Tipe evidence baru tidak butuh migrasi (kolom `type` sudah string).
- [x] Model `CollectionAccountState`, `CollectionActivity`, `CollectionNote`, `CollectionSyncLog`, `DeviceToken`; kolom & relasi baru di `Unit` (`collectionState`, `collectionActivities`, `collectionNotes`, `billingPayerResidentId()`), `PaymentPromise`, `CollectorVisit`, `PaymentTransaction`, `CollectorAssignment`, `CollectorTarget`.
- [x] Trait `HasClientUuid` dan `HasVersion`. Saat ini dipakai `CollectionNote`; model lama (PTP/visit/payment) baru memakai `HasVersion` di tahap fiturnya masing-masing (T7–T9).
- [x] Permission `collection-*` di `RolePermissionSeeder` untuk collector/supervisor/finance/admin_estate/property_manager.

**Verifikasi:** migrasi `down` → `up` lolos di sqlite. **Belum** diuji di MySQL staging.

### T2 — Service inti · L · ✅ selesai (8 Okt 2026)
**Tergantung:** T1
- [x] `CollectionAgingService` — bucket hari + `breakdown()` untuk dashboard. *Keputusan:* `ReceivableController::aging` **tidak** direfaktor karena memakai tier bulan untuk denda (konsep berbeda); keduanya dibiarkan terpisah.
- [x] `CollectionAccountService` — `computeMany`, `refresh`, `refreshMany`, `refreshAll`, `resolveStatus`, `openInvoices`. Data pendukung dimuat per chunk 200 unit dengan query agregat (tanpa N+1).
- [x] `CollectionPriorityService` — skor & level dari config; akun disputed maksimal `medium`.
- [x] `CollectorAssignmentService::primaryCollectorIdsFor()` — collector utama per unit (unit > resident > block > cluster).
- [x] `CollectionActivityService` — `record()` untuk aktivitas manual (idempoten via `client_uuid`, diaudit) dan `recordForSubject()` untuk aktivitas turunan (idempoten per subjek+event).
- [x] `CollectionTimelineService` — cursor pagination.
- [x] **Tambahan:** `CollectionTimelineObserver` (visit, PTP, pembayaran, pengingat WA → timeline) dan `BillingCollectionObserver` (perubahan tagihan → refresh). Keduanya berjalan setelah commit dan tidak pernah melempar error ke alur bisnis. Refresh digabung per request lewat `CollectionAccountRefreshQueue` (flush saat terminating & setelah tiap job). Akibatnya endpoint lama otomatis mengisi timeline, dan T6–T9 tidak perlu menulis aktivitas sendiri untuk subjek-subjek ini.
- [x] Command `collection:refresh-account-states [--unit=]` (terjadwal per jam) dan `collection:backfill-activities [--since=] [--skip-refresh]` (idempoten).
- [x] Test: `tests/Unit/CollectionAgingServiceTest`, `tests/Unit/CollectionPriorityServiceTest`, `tests/Feature/CollectionAccountServiceTest` (22 test).

**Langkah deploy T1+T2** (dijalankan tim deploy, bukan dari sesi pengembangan):
1. `php artisan migrate`
2. `php artisan db:seed --class=RolePermissionSeeder` — seeder ini memakai `syncPermissions`, jadi permission role yang diubah manual lewat UI akan kembali ke definisi seeder.
3. `php artisan collection:backfill-activities` — mengisi timeline dari data lama sekaligus menghitung status semua akun.

### T3 — API baca collector · M
**Tergantung:** T2
- [ ] Grup route `collector` (role collector) dan `collection` (supervisor/manager/finance) di `routes/api.php`.
- [ ] `GET /collector/dashboard` (`CollectionDashboardService`, satu query agregat per widget, cache 60 dtk per collector).
- [ ] `GET /collector/accounts` + filter lengkap (§11 brief) memakai `App\Support\UnitFilters` + state cache; eager loading untuk cegah N+1.
- [ ] `GET /collector/accounts/{unit}`, `/bills`, `/activities`.
- [ ] `GET /collection/accounts` (scope supervisor per cluster), `GET /collector/assignments`.
- [ ] API Resource class untuk shape response yang dipakai web & Flutter.
- [ ] Test: scoping (collector A ≠ B → 403), filter kombinasi, pagination, angka dashboard = angka list.
- [ ] Tambah ke `docs/openapi.yaml`.

**Selesai bila:** semua endpoint terdokumentasi & teruji; **kontrak API dibekukan** agar jalur web dan Flutter bisa mulai paralel.

### T4 — Komponen bersama web · M
**Tergantung:** T3 (kontrak)
- [ ] `StatCard`, `ChartCard` (warna dari token antd → aman dark mode), `AgingChart`, `TargetProgress`, `PriorityBadge`, `CollectionTimeline`, `AccountCardList`, `CollectorSelect`, `PageSkeleton`, `ConfirmAmountModal`.
- [ ] Tambah map status di `StatusBadge` (collectionAccount, visitLifecycle, ptp, collectionPayment, dispute, escalation).
- [ ] `api.collector.*` dan `api.collection.*` di `services/estateApi.js`; hook `useCollectorAccounts`, `useCollectorDashboard`.
- [ ] Menu collector di `constants/permissions.js`; route di `AppRoutes.jsx`; **redirect role collector dari `/` ke `/collector`** (perbaiki `/403`).

**Selesai bila:** `npm run lint` + `npm run build` lolos; komponen dicek di light & dark mode.

### T5 — Halaman inti collector (web) · L
**Tergantung:** T4
- [ ] Collector Dashboard (KPI, target vs actual, aging, priority, priority accounts, today's visits).
- [ ] My Collection (tab view, filter, tabel desktop / kartu mobile, empty state).
- [ ] Collection Detail (header, action bar, tab Overview/Bills/Timeline; tab lain diisi bertahap di T6–T10).
- [ ] Collection Monitoring untuk supervisor (halaman yang sama + filter collector).

**Selesai bila:** UAT singkat dengan 1 collector & 1 supervisor → **Milestone M1**.

### T6 — Contact activity & notes · M
**Tergantung:** T3 (backend), T5 (UI)
- [ ] `POST /collector/activities` (call/whatsapp/sms/email + `channel_result` + `next_follow_up_at`).
- [ ] CRUD `collector/notes` + attachment via `managed_files`; pastikan tidak bocor ke endpoint resident (test).
- [ ] Hitung `failed_contact_count`, `last_contact_at`, `next_follow_up_at` di state.
- [ ] Web: tombol Call/WA → modal Contact Result; tab Notes; halaman Activities.
- [ ] Migrasi data: `collector_reminders` lama ikut ditampilkan di timeline (type whatsapp, tanpa result).

### T7 — Payment + verifikasi (D1) · L · *risiko tertinggi*
**Tergantung:** T3
- [ ] `CollectionPaymentService`: `submit` (provider `collector`, `waiting_verification`, tidak menyentuh billing), `attachProof`, `requestRevision`, `resubmit`, `approve` (→ `PaymentService::settleGatewayTransaction`, dalam transaksi + `lockForUpdate`), `reject` (alasan wajib).
- [ ] Validasi ulang di server: invoice milik unit, nominal ≤ outstanding saat approve (bukan saat submit), `verified_by ≠ collected_by`.
- [ ] Feature flag `collector.payment_requires_verification`; jika `false` → alur lama `PaymentService::process`.
- [ ] Perbaiki `CollectorPerformanceService`: collected = `collected_by` (baru) + `loket/created_by` (data lama), hanya status `paid`.
- [ ] Notifikasi in-app: Payment Verified / Rejected / Revision Requested; supervisor notification "menunggu verifikasi" (generator existing).
- [ ] Web collector: Payment List / Create / Detail. Web finance: Verification Queue.
- [ ] Test: cash, transfer, partial, proof, approve, reject, revision, double-approve, approve setelah billing dibayar di loket (harus gagal dengan pesan jelas), regresi `PaymentFlowTest` & `LoketPaymentVerificationAndPrintTest`.

### T8 — Visit · M
**Tergantung:** T6
- [ ] `CollectionVisitService`: schedule, start (GPS + timestamp), submit result (`result_code` → isi juga `status` lama), cancel; aturan tanda tangan/foto per hasil (D6).
- [ ] Evidence type baru (selfie/property/payment_proof); hasil gagal menaikkan `failed_visit_count`.
- [ ] Endpoint lama `units/{u}/visits` & `visits/{v}` tetap bekerja (regresi `CollectorVisitSignatureTest`).
- [ ] Web: Visit List (today/upcoming/completed/failed), Detail (foto, peta, durasi), Create.

### T9 — Promise to Pay · M
**Tergantung:** T7 (fulfilled bergantung pada payment terverifikasi)
- [ ] `PromiseToPayService`: create (isi `collector_id`, `visit_id`), cancel (alasan), `fulfill` dipanggil dari `CollectionPaymentService::approve`.
- [ ] Command harian `collection:evaluate-promises` → `broken` + aktivitas + supervisor notification (generator `broken_promise` existing jadi berguna).
- [ ] `GET /collection/promises` + summary (total, due today, due tomorrow, fulfilled, broken, success rate).
- [ ] Web: PTP List/Detail collector, PTP Monitoring + chart fulfillment rate.

**Selesai bila:** bersama T14 → **Milestone M2**.

### T10 — Dispute & Escalation · L
**Tergantung:** T6
- [ ] Migrasi `collection_disputes`, `collection_escalations`, `collection_escalation_steps`.
- [ ] `CollectionDisputeService` (open → under_review → resolved/rejected), `CollectionEscalationService` (create manual, forward supervisor → estate manager → legal, resolve).
- [ ] Auto-trigger eskalasi (command harian, ambang dari config, akun disputed dikecualikan, tidak duplikat).
- [ ] Web collector: form & list; web supervisor: Dispute Monitoring, Escalation Monitoring (inbox + timeline step).
- [ ] Test: billing & outstanding tidak berubah oleh dispute/eskalasi (R8, R9).

### T11 — Supervisor management · M
**Tergantung:** T7–T10
- [ ] `POST /collection/assignments/bulk` (manual bulk & area), reassign wajib `reason` + `reassigned_from_id`.
- [ ] `CollectorAssignmentsPage`: mode Manual / Bulk / Area + histori reassign.
- [ ] Target: kolom akun & collection rate di `CollectorTargetsPage`.
- [ ] Performance: chart tren + ranking multi-metric (`/collection/performance?metric=`).
- [ ] Visit Monitoring, dashboard supervisor ditambah chart.

### T12 — Reports & export · M
**Tergantung:** T11, D4
- [ ] `CollectionReportService` — 6 report (collection, aging, collector, visit, PTP, payment), memakai service yang sama dengan dashboard (angka identik).
- [ ] Export xlsx (library D4) + PDF (DomPDF); report besar lewat `report_exports` + job existing.
- [ ] Web: halaman Reports (collector: limited, supervisor/finance: lengkap).

**Selesai bila:** angka report = angka dashboard untuk periode yang sama (test) → **Milestone M3**.

### T13 — Fondasi Flutter · L · *bisa mulai setelah T3*
**Tergantung:** T3, D2
- [ ] Tambah `flutter_riverpod`, `go_router`, `drift`, `uuid`, `connectivity_plus`; struktur `lib/src/features/collector/{data,domain,presentation}`.
- [ ] Model bertipe + repository untuk akun, tagihan, aktivitas (dari kontrak T3).
- [ ] `go_router` hanya untuk shell collector; shell customer/supervisor tetap memakai navigasi lama.
- [ ] Manifest: `<queries>` tel/https/geo, permission `CAMERA`; `applicationId` final (koordinasi rilis).
- [ ] Helper intent: `tel:`, `wa.me`, `geo:` + fallback Google Maps.

**Selesai bila:** `flutter analyze` bersih; login collector masuk ke shell baru; login customer/supervisor tidak berubah.

### T14 — Layar collector Flutter (online) · L
**Tergantung:** T13, mengikuti backend T6–T10
- [ ] Bottom nav baru: Home / Collection / Visits / Activity / Profile.
- [ ] Home, Collection List (kartu + search + chip filter), Collection Detail (quick action + tab).
- [ ] Contact Result bottom sheet setelah kembali dari Call/WA (T6).
- [ ] Visit: start → result → foto → notes → submit (T8).
- [ ] Payment: invoice → nominal → metode (dari API) → bukti → review → konfirmasi (T7).
- [ ] PTP, Note, Dispute, Escalation (T9, T10).
- [ ] Notifikasi: perbaiki routing staff di `notification_router.dart`.

### T15 — Offline sync · L
**Tergantung:** T14
- [ ] Backend: `CollectionSyncService`, `POST /collector/sync` (batch, idempoten via `client_uuid`, konflik via `base_version`), `GET /collector/sync/bootstrap?since=` (delta, dipaginasi), tulis `collection_sync_logs`.
- [ ] Flutter: tabel drift (cache + outbox), semua aksi T14 menulis lokal dulu lalu outbox; `workmanager` untuk flush; foto disimpan lokal hingga terunggah.
- [ ] Payment offline hanya **draft**, submit setelah online + bukti terunggah.
- [ ] Sync Center: last synced, antrian, gagal, konflik ([Refresh] [Review Changes]).
- [ ] Test backend: duplikat, konflik, item tidak valid tidak menggagalkan item lain; uji manual mode pesawat.

### T16 — Push notification · M
**Tergantung:** T15, D3 (project Firebase)
- [ ] Backend: `POST/DELETE /devices`, channel `push` di pengiriman notifikasi (job antrean).
- [ ] Event: PTP due today, PTP broken, new assignment, visit reminder, payment verified/rejected, dispute/escalation update, pesan supervisor.
- [ ] Flutter: `firebase_messaging` + `flutter_local_notifications`, deep link ke akun/PTP/payment, permission `POST_NOTIFICATIONS`.

### T17 — Hardening, UAT & rollout · M
**Tergantung:** semua
- [ ] Audit N+1 (query log di test), index sesuai pola filter, cache dashboard.
- [ ] Review keamanan: setiap endpoint `/collector/*` & `/collection/*` diuji akses lintas role.
- [ ] UAT per role dengan skenario §14; perbaikan.
- [ ] Rollout produksi berurutan:
  1. Deploy backend + migrasi (dijalankan oleh tim deploy, di luar sesi pengembangan).
  2. `php artisan collection:refresh-account-states` (backfill).
  3. Seeder permission.
  4. Aktifkan scheduler (`evaluate-promises`, auto-eskalasi, refresh state).
  5. Deploy web.
  6. Rilis APK ke collector pilot (1–2 orang) → seluruh collector.
  7. Nyalakan `payment_requires_verification` setelah Finance siap dengan antrean verifikasi.
- [ ] Update `docs/REVISION_ROADMAP.md` dan `docs/DEPLOYMENT.md`.

### 13.4 Ringkasan estimasi

| Jalur | Tahap | Estimasi (1 dev) |
|---|---|---|
| Fondasi | T0–T3 | ± 3–4 minggu |
| Web + backend fitur | T4–T12 | ± 7–9 minggu |
| Flutter | T13–T16 | ± 6–8 minggu (paralel mulai minggu ke-4) |
| Hardening & rollout | T17 | ± 1–2 minggu |
| **Total** | | **± 12–14 minggu dengan 2 developer paralel**; ± 18–22 minggu dengan 1 developer |

Setiap tahap tidak mengubah perilaku loket, resident portal, atau billing, kecuali D1 di T7 (di balik feature flag).

---

## 14. Skenario Test (ringkas)

- **Assignment:** assign, bulk assign, area assign, reassign (audit old/new/by/at/reason), collector A mengakses unit collector B → 403.
- **Collection:** status due_today / due_soon / overdue / paid / partially_paid; aging bucket batas (30/31, 180/181); priority level.
- **Visit:** jadwal → start → result sukses (wajib tanda tangan) / gagal (wajib foto), GPS tersimpan hanya saat start/finish.
- **PTP:** create, fulfill otomatis setelah verifikasi, broken via job, cancel dengan alasan; PTP tidak mengubah billing.
- **Payment:** cash, transfer, partial, proof upload, approve (billing ter-settle dalam transaksi), reject (alasan wajib, billing tidak berubah), request revision → edit → approve; collector tidak bisa approve miliknya.
- **Dispute:** create, under_review, resolve, reject; billing tetap utuh.
- **Escalation:** manual, auto-trigger, forward supervisor → manager → legal, resolve; outstanding tidak berubah.
- **Offline:** item dikirim dua kali → `duplicate`; `base_version` usang → `conflict`; draft payment tidak tersubmit tanpa bukti.

> Catatan lingkungan: `.env` lokal mengarah ke database remote. Pengembangan & verifikasi dilakukan lewat test PHPUnit (sqlite in-memory); jangan menjalankan `migrate`/`seed`/`serve` terhadap `.env` ini.
