# Revision Roadmap — Estate Management (Duta Residence)

Dokumen ini merangkum urutan pengerjaan untuk daftar revisi & pengembangan yang diminta (Juli 2026), disusun berdasarkan hasil audit codebase (Laravel 12 backend, React/Ant Design web SPA, Flutter customer mobile app) dan dependency antar fitur, agar pengembangan bisa berjalan efisien: quick win dulu untuk momentum cepat, fondasi data sebelum fitur besar, dan Collector sebelum Supervisor karena Supervisor memonitor data yang dihasilkan Collector.

## Decisions Log

Keputusan berikut sudah dikonfirmasi dan menjadi dasar seluruh roadmap di bawah — tidak perlu ditanyakan ulang:

| Keputusan | Pilihan |
|---|---|
| Platform aplikasi Collector | Role baru di dalam app **Flutter Customer** yang sudah ada (bukan app terpisah, bukan web) |
| Formula akumulasi denda | **Saldo berjalan** (running balance): denda bulan lalu yang belum dibayar + denda baru bulan ini, terbawa terus sampai lunas |
| Prioritas rilis | **Quick wins dulu** (rilis cepat, low-risk, lintas modul), baru proyek besar secara bertahap |

## Temuan Kunci dari Codebase

- RBAC pakai **Spatie Laravel-Permission** (`database/seeders/RolePermissionSeeder.php`) — pola sudah reusable untuk role baru. Role `collector` **sudah ada** (permission dasar `visits.*`, `payment-promises.*`, `payments.process`); role `supervisor` **belum ada sama sekali**.
- `CollectorVisit` model sudah punya field GPS check-in (`checkin_latitude`/`checkin_longitude`) — snapshot per-kunjungan, bukan live tracking. Pondasi parsial untuk modul lapangan Collector.
- WhatsApp (Fonnte) baru **config placeholder** (`config/grandduta.php` → `fonnte_token`), belum dipakai di kode manapun — reminder/broadcast WA perlu dibangun dari nol.
- Export PDF (`DocumentController` + `resources/views/pdf/*`) dan export "Excel" (sebenarnya CSV streaming) sudah ada pola reusable untuk seluruh laporan Supervisor.
- Tidak ada model akuntansi (Chart of Accounts/Jurnal) sama sekali — Neraca adalah subsistem baru, bukan sekadar laporan tambahan.
- `PenaltyService::calculatePenalty()` saat ini **sengaja tier-based dan tidak akumulatif** (ada komentar eksplisit di kode: "bukan akumulasi per bulan") — redesign ke saldo berjalan adalah perubahan pada logika inti billing, bukan penambahan field biasa.
- Virtual Account saat ini **satu setting global** (`PaymentGatewaySetting`), bukan per-unit. Midtrans sudah terintegrasi (`MidtransPaymentService.php`) — kandidat utama untuk VA per-unit lewat Core API fixed/closed VA.
- Partial payment **sudah didukung di backend** (`Billing::STATUS_PARTIAL`, alokasi multi-billing di `PaymentService`) — kemungkinan hanya perlu verifikasi/exposure di sisi customer, bukan dibangun dari nol.
- Homepage publik, Maintenance Mode toggle, dan Single Session Login **tidak ada sama sekali** — ketiganya net-new.

## Ringkasan Stage

| Stage | Fokus | Estimasi | Dependency |
|---|---|---|---|
| A | Quick wins & stabilisasi | 1–2 minggu | Tidak ada — bisa dikerjakan paralel antar item |
| B | Fondasi data & kebijakan | 2–3 minggu | Setelah Stage A (regresi lebih aman di codebase yang sudah dirapikan) |
| C | Ekspansi fitur customer | 2–3 minggu | Sebagian butuh field/setting dari Stage B |
| D | Role Collector (19 modul) | 4–6 minggu | Independen, tapi idealnya setelah Stage B (kategori tagihan, denda) stabil |
| E | Role Supervisor (50 modul) | 6–10 minggu | Bergantung data & modul dari Stage D |
| F | Balance Sheet / Neraca | 4–8 minggu | Track paralel independen, mulai setelah Stage B (data billing/denda stabil) |

---

## Stage A — Quick Wins & Stabilisasi

Item kecil, isolated, low-risk. Cocok dikerjakan paralel oleh beberapa developer sekaligus dan dirilis sebagai satu batch cepat.

- **Hapus menu Maintenance untuk role Customer** — web: `frontend/src/constants/permissions.js` (entry `roles: ['customer']`); Flutter: tab Maintenance di `services_screen.dart`.
- **Audit & rename `emergency_alert` → "Emergency Alert"** — literal string ditemukan di `ResidentPortalController::emergency()` (tipe notifikasi); sisanya label UI sudah Indonesia ("Sinyal Darurat"/"Darurat") — audit menyeluruh Web & Android untuk konsistensi penamaan.
- **Aktifkan kembali card "Akses Layanan"** di `android/app/lib/src/screens/dashboard_screen.dart`.
- **Pencarian Penghuni** berdasarkan Alamat/Cluster/Blok — extend filter pada endpoint & halaman daftar penghuni existing.
- **Tambah tipe properti "Ruko"** — `database/seeders/EstateSeeder.php` (`propertyTypeOptions`, saat ini hanya B/K/P).
- **Tambah status Occupancy "Siswa"** — tabel `occupancy_statuses` (saat ini hanya Dihuni/Kosong).
- **Total Tagihan** di halaman tagihan penghuni — agregasi tampilan, `ResidentDetailPage.jsx`/`BillingsPage.jsx`.
- **Hapus komponen Pajak** dari tampilan penagihan — field `tax` di `PaymentTransaction` sudah selalu 0, hanya perlu hapus dari tampilan (`ResidentPortalPage.jsx`, dsb).
- **Access Denied untuk direct-URL Dashboard Customer** tanpa hak akses — verifikasi/perbaiki wrapper `<Protected>` di `frontend/src/routes/AppRoutes.jsx`.
- **Sembunyikan tombol "Tambah Complaint"** bila tidak diizinkan — permission-gated rendering, cek existing complaint/komentar page.
- **Single Session Login (Android)** — backend: revoke token lama saat login baru (`AuthController`, saat ini tidak ada `$user->tokens()->delete()`); perlu mekanisme notifikasi realtime ke device lama (push/forced-401) + handling di `session_controller.dart` Flutter.
- **Shape editing di Peta Cluster** — tambah kontrol ubah `shape_type` object existing di `ClusterMapObjectPanel.jsx` (saat ini fixed saat insert) + endpoint update backend.
- **Verifikasi Partial Payment** end-to-end di sisi customer — backend sudah mendukung, pastikan sudah ter-expose penuh di flow pembayaran customer; jika belum, tambahkan di Stage C bersamaan dengan VA per-unit.

## Stage B — Fondasi Data & Kebijakan

Perubahan pada skema/inti bisnis logic yang menjadi pondasi Stage C–F. Butuh review lebih ketat sebelum production.

- **Field Virtual Account pada form Unit** — migration + field baru di `UnitForm.jsx`/`Unit` model, sebagai pondasi VA per-unit di Stage C.
- **Kategori Tagihan** — buat master data kategori (model/migration/controller/admin UI CRUD), minimal kategori "Unit"; saat ini hanya free-text `billing_type` tanpa CRUD.
- **Redesign Denda ke saldo berjalan** ⚠️ *risiko tertinggi di stage ini* — ubah `PenaltyService` dari tier-recalculation ke akumulasi saldo berjalan. Perlu: rencana migrasi data denda existing (agar tidak retroaktif tidak adil ke penghuni), regression test menyeluruh terhadap alur billing, dan sign-off sebelum rilis ke production.
- **Maintenance Mode setting (Admin)** — setting global baru (flag + endpoint status) sebagai pondasi enforcement di Stage berikutnya (lihat item terkait di bawah).
- **Enforcement Maintenance Mode** — middleware/guard di rute Customer web (halaman "Under Maintenance", fitur nonaktif sementara) dan pengecekan setara di bootstrap sesi Flutter.
- **Emergency — GPS location saat lapor** — izin lokasi + capture lat/long di `_triggerEmergency` (Flutter), tambah kolom lat/long di migration `EmergencyAlert`, terima & simpan di `ResidentPortalController::emergency()`.
- **Emergency — status "Menunggu Respons Admin"** — tambah field status workflow (pending/acknowledged/resolved) di `EmergencyAlert`, tampilkan ke resident hingga admin bertindak.

## Stage C — Ekspansi Fitur Customer

- **Request Sewakan Unit** — form request di app Customer (aktif hanya jika status unit mengizinkan sewa) → dikirim ke Admin untuk approval; model/controller/notifikasi baru + UI web & Flutter.
- **Virtual Account per Unit** — integrasi Midtrans Core API (fixed/closed VA per customer) memakai field VA dari Stage B; perlu konfirmasi ke Midtrans apakah akun saat ini mendukung fitur ini, fallback ke Xendit Fixed VA bila tidak.
- **Penyelesaian Partial Payment di sisi customer** — jika hasil verifikasi Stage A menunjukkan belum ter-expose penuh, selesaikan di sini bersamaan dengan perubahan alur pembayaran VA per-unit.
- **Homepage Publik** (Slider/Banner, Event, Iklan/Promosi, Kerja Sama, Testimoni, Contact Us, Footer) — modul CMS baru (model/migration/CRUD admin per section + halaman publik). **Independen dari sistem role/billing** — cocok dikerjakan tim/dev terpisah secara paralel dengan Stage D/E tanpa saling menunggu.

## Stage D — Role Collector (19 Modul)

Dibangun sebagai role baru di dalam app Flutter Customer existing (role-switch setelah login menentukan shell/dashboard yang tampil). Role `collector` di backend sudah ada sebagian; berikut urutan internal yang disarankan agar tiap modul bisa dibangun di atas modul sebelumnya:

1. Role-switch di app shell (Dashboard Collector muncul untuk user berrole collector)
2. Daftar Penghuni Tanggung Jawab & Detail Penghuni (baca data existing)
3. Riwayat Penagihan (baca data existing)
4. Kunjungan Lapangan — reuse field GPS di `CollectorVisit`
5. Pembayaran di Tempat — reuse `ManualPaymentService`
6. Cicilan Tunggakan — reuse model `Installment`
7. Promise To Pay (Janji Bayar) — modul baru
8. Reminder WhatsApp — modul baru, butuh integrasi Fonnte (baseline yang juga dipakai Broadcast WA di Stage E)
9. Route Kunjungan, Target Collector, Notifikasi
10. Komplain Saat Penagihan, Upload Bukti, Surat Penagihan — reuse pola PDF `DocumentController`
11. Status Penagihan
12. Monitoring Lokasi Collector — upgrade dari snapshot check-in ke live/periodic tracking (paling kompleks, ditaruh terakhir karena jadi dasar Live Tracking di Stage E)
13. Emergency (Keadaan Darurat) — reuse `EmergencyAlert` dari Stage B
14. Hak Akses (Role Collector) — finalisasi permission di Spatie seeder

**Ketentuan khusus:** GPS wajib selalu aktif — app tidak bisa dipakai Collector bila location service dimatikan (enforcement di level app shell/session, bukan hanya di modul kunjungan).

## Stage E — Role Supervisor (50 Modul)

Role baru, belum ada sama sekali di backend. Karena mayoritas modul bersifat monitoring/approval atas data yang dihasilkan Collector, stage ini **harus** berjalan setelah modul terkait di Stage D tersedia. Dikelompokkan menjadi sub-fase:

- **(a) Dasar** — Dashboard Supervisor, Hak Akses Supervisor (role baru di seeder), Notifikasi Supervisor.
- **(b) Monitoring dasar** — Monitoring Seluruh/Daftar/Detail Collector, Monitoring Target, Progress Penagihan, Tunggakan, Promise To Pay, Broken Promise — reuse data dari Stage D.
- **(c) Approval workflow** (5 jenis, masing-masing state-machine + notifikasi + audit baru): Cicilan Tunggakan, Penyesuaian Tagihan, Penghapusan Denda, Pembatalan Transaksi, Pembatalan Pembayaran.
- **(d) Wilayah & penugasan** — Distribusi Wilayah, Penugasan Collector, Reassign Wilayah, Jadwal Kunjungan Collector.
- **(e) Live tracking & peta** — Live Tracking Collector, Dashboard Peta Cluster (Map Monitoring), Heatmap Tunggakan per Area — bergantung Monitoring Lokasi live dari Stage D #12.
- **(f) Broadcast & komunikasi** — Broadcast WhatsApp ke Collector/Penghuni, Pengumuman Internal — bergantung integrasi Fonnte dari Stage D #8.
- **(g) Laporan & analisis** — Laporan Harian/Mingguan/Bulanan Collector, Analisis Piutang, Analisis Tunggakan per Cluster (jumlah unit menunggak, total nominal, persentase per cluster), Export Laporan PDF & Excel — reuse pola `ReportController`/`DocumentController`.
- **(h) Monitoring lanjutan** — Monitoring Komplain Penghuni, Emergency, Unit Kosong/Disewakan/Bermasalah, Pelanggaran Penghuni, Dokumentasi Lapangan, Bukti Pembayaran/Kunjungan, Surat Penagihan.
- **(i) Governance** — Audit Log Aktivitas (cek apakah middleware `audit` existing sudah cukup untuk expose UI), Manajemen Prioritas Penagihan, Pusat Notifikasi & Eskalasi.

## Stage F — Balance Sheet / Neraca (Super Admin)

Track paralel independen — tidak bergantung pada Stage D/E, bisa mulai kapan saja setelah Stage B (agar data billing/denda yang mengalir sudah stabil).

- Tidak ada model akuntansi sama sekali saat ini (`Billing`, `Receipt`, `PaymentTransaction`, `Installment`, `Reversal` bersifat transaksional, bukan double-entry).
- **Rekomendasi:** sesi desain terpisah dengan stakeholder finance sebelum mulai coding — perlu Chart of Accounts, struktur Jurnal, aturan mapping transaksi existing ke jurnal entry, dan periode closing, sebelum laporan Neraca bisa digenerate.

---

## Rekomendasi Paralelisasi

Jika tersedia lebih dari satu developer, urutan di atas tidak harus 100% sekuensial:

- **Stage C (Homepage Publik)** tidak menyentuh sistem role/billing — bisa dikerjakan tim terpisah secara paralel dengan Stage D/E kapan saja.
- **Stage F (Neraca)** juga independen dari Collector/Supervisor — bisa berjalan sebagai track finance-led paralel setelah Stage B selesai.
- **Stage D → Stage E wajib berurutan** untuk modul yang saling bergantung (live tracking, broadcast WA, approval atas data Collector) — jangan mulai bagian Supervisor yang bergantung data sebelum modul Collector terkait selesai.

## Risiko yang Perlu Perhatian Khusus

1. **Redesign Denda (Stage B)** — perubahan logika inti billing dari tier-based ke saldo berjalan; wajib regression test menyeluruh + rencana migrasi data existing sebelum rilis.
2. **VA per-Unit (Stage C)** — bergantung konfirmasi kapabilitas gateway (Midtrans fixed/closed VA); jika tidak didukung, perlu evaluasi ulang gateway sebelum lanjut.
3. **Dependency Collector → Supervisor (Stage D → E)** — jangan mengerjakan modul Supervisor yang memonitor data lapangan (live tracking, broadcast WA, approval) sebelum modul Collector terkait tersedia dan diuji.
