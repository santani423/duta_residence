# Checklist UI Frontend — Modul Collector (Web)

Daftar hal yang dapat dicek secara manual pada **web frontend** (`frontend/`) untuk semua halaman yang berkaitan dengan collector: halaman role collector, manajemen collector (admin/supervisor), monitoring penagihan, dan halaman supervisor.

Dokumen ini adalah checklist praktis untuk dicentang saat menguji. Kasus uji formal (dengan langkah, data uji, prioritas, dan severitas) ada di [13_collector_module.md](13_collector_module.md) dan [14_supervisor_module.md](14_supervisor_module.md). Aplikasi Android **tidak** dicakup di sini.

- **Basis kode:** commit `7ccde777` (8 Okt 2026).
- **Kode item:** `UI-COL-xxx`, supaya mudah dirujuk di laporan bug.

---

## 0. Cara Memakai Checklist Ini

### Arti status

| Tanda | Arti |
|---|---|
| ✅ | **Bisa dicek sekarang**: halaman dan backend sudah mendukung. |
| ⚠️ | **Sebagian**: halaman tampil, tetapi sebagian data atau aksi belum berfungsi. Alasannya tertulis di item. |
| ⛔ | **Terblokir**: belum bisa diuji. Alasannya tertulis di item (endpoint masih stub 501, method backend belum ada, atau halaman gagal di-build). Item ini **tetap dicantumkan** supaya bisa langsung diuji setelah backend selesai. |

Saat mengisi, centang `[x]` bila sesuai harapan. Bila tidak sesuai, tulis catatan di bawah item, misalnya `> ✘ muncul error 500`.

### Prasyarat

1. Jalankan frontend di lingkungan **dev/staging, bukan produksi**.
2. Database uji sudah berisi data seeder: cluster, unit, tagihan, collector, dan supervisor.
3. Akun uji (password semua: `password`):

| Role | Username | Catatan |
|---|---|---|
| super_admin | `superadmin` | Melihat semua data |
| admin_estate | `admin.estate` | Mengelola collector |
| property_manager | `property.manager` | Hanya melihat (tanpa aksi tulis) |
| finance | `finance` | Tidak semestinya melihat menu manajemen collector |
| supervisor | `supervisor.rina` | Cluster Alamanda & Bougenville (collector Budi & Siti) |
| supervisor | `supervisor.hendra` | Cluster Cendana & Dahlia (collector Ahmad & Dewi) |
| collector aktif | `kolektor.budi`, `kolektor.siti`, `kolektor.ahmad`, `kolektor.dewi` | |
| collector tidak aktif | `kolektor.rudi` (inactive), `kolektor.maya` (cuti), `kolektor.eko` (suspend) | Login semestinya ditolak |

4. Untuk item yang menyentuh data **status akun penagihan** (tunggakan, aging, prioritas), jalankan dulu `php artisan collection:backfill-activities` di lingkungan uji supaya cache `collection_account_states` terisi.

### ⚠️ Penghalang umum saat ini

Penghalang ini perlu diselesaikan sebelum banyak item ⛔ bisa diuji:

| # | Penghalang | Dampak di UI |
|---|---|---|
| B-1 | `CollectorAssignmentsPage.jsx` baris 461, 1114, 1416: ekspresi `{...(x.isError ? … )}` tidak ditutup | `npm run build` **gagal**. Di mode dev, halaman Penugasan Kolektor error saat dibuka. |
| B-2 | `GET /collection/collectors/options` masih stub 501 | **Semua dropdown "Pilih kolektor" kosong** di halaman Data Kolektor (serah terima), Detail, Penugasan, Target, Performa, dan Monitoring. |
| B-3 | `GET /collection/accounts` stub 501 | Halaman Monitoring Penagihan dan tab Portofolio Akun menampilkan error. |
| B-4 | `POST /collection/assignments/preview`, `/bulk`, `GET /collection/assignments/unassigned-units` stub 501 | Mode Massal/Area, Ringkasan Cakupan, dan tab Unit Belum Ditugaskan tidak berfungsi. |
| B-5 | `GET /collection/performance` stub 501 | Peringkat performa (admin/supervisor) error. |
| B-6 | `GET /collector-targets/progress`: method `progress` belum ada di backend | Tab **Progres** (tab default) di Target Kolektor error 500. |
| B-7 | Backend Collector & Assignment belum diperbarui (tahap B1/B2) | Edit kolektor 422, edit penugasan 422, reassign tanpa catatan 500, statistik baris kosong, serah terima tidak dipicu. |

---

## 1. Navigasi, Login & Shell Aplikasi

### 1.1 Login & halaman awal

- [ ] **UI-COL-001** ✅ Login sebagai `kolektor.budi`. → Login berhasil dan masuk ke aplikasi (tidak ke halaman `/403`).
- [ ] **UI-COL-002** ⚠️ Halaman pertama setelah collector login. → *Desain:* dashboard collector. *Saat ini:* collector masuk ke Dashboard umum estate karena dashboard khusus collector belum dibuat (T3–T5). Catat apa yang tampil.
- [ ] **UI-COL-003** ✅ Login sebagai `kolektor.rudi` / `kolektor.maya` / `kolektor.eko`. → Ditolak dengan pesan akun tidak aktif. Tidak ada token tersimpan.
- [ ] **UI-COL-004** ✅ Saat collector sedang login, nonaktifkan akunnya dari akun admin, lalu klik menu apa saja di sesi collector. → Request ditolak (401), sesi berakhir, dan pengguna diarahkan ke halaman sesi berakhir/login.
- [ ] **UI-COL-005** ✅ Login sebagai `supervisor.rina`. → Menu grup **Supervisor** dan **Manajemen Kolektor** tampil.

### 1.2 Menu sidebar per role

- [ ] **UI-COL-010** ✅ Collector: menu yang tampil adalah **Target & Performa**, **Rute Kunjungan**, **Pengingat WhatsApp**, **Surat Penagihan**, **Notifikasi**, **Profil**, **Ganti Password**.
- [ ] **UI-COL-011** ⚠️ Collector: periksa apakah muncul grup **Manajemen Kolektor** tambahan (berisi Performa / Surat Penagihan). → Semestinya **tidak** muncul. Ada laporan menu ganda akibat perubahan gate menu induk; catat bila muncul.
- [ ] **UI-COL-012** ✅ admin_estate: grup **Manajemen Kolektor** berisi **Monitoring Penagihan**, **Data Kolektor**, **Penugasan Kolektor**, **Target Kolektor**, **Performa Kolektor**, **Lokasi Real-Time**, **Surat Penagihan**.
- [ ] **UI-COL-013** ✅ supervisor: grup **Supervisor** berisi **Dashboard**, **Monitoring Kolektor**, **Pusat Persetujuan**, **Analisis Tunggakan**, **Peta Pemantauan**, **Notifikasi & Eskalasi**, **Broadcast**, **Laporan**.
- [ ] **UI-COL-014** ✅ finance: grup **Manajemen Kolektor** dan **Supervisor** **tidak** tampil.
- [ ] **UI-COL-015** ⚠️ property_manager: hanya menu baca untuk collector. Permission baru di seeder perlu di-seed ulang di lingkungan uji. Tombol Tambah/Edit/Hapus **tidak** boleh muncul.
- [ ] **UI-COL-016** ✅ Grup menu induk tampil bila minimal satu sub-menu boleh diakses, dan tersembunyi bila tidak ada satu pun.
- [ ] **UI-COL-017** ✅ Menu aktif ter-highlight sesuai URL; sub-menu induknya otomatis terbuka.

### 1.3 Proteksi route

- [ ] **UI-COL-020** ✅ Collector mengetik URL `/admin/collectors/list`. → Diarahkan ke `/403`.
- [ ] **UI-COL-021** ✅ Collector mengetik `/collection/monitoring`. → `/403`.
- [ ] **UI-COL-022** ✅ admin_estate mengetik `/collector/route`. → `/403` (route khusus role collector).
- [ ] **UI-COL-023** ✅ Tanpa login, buka `/admin/collectors/list`. → Diarahkan ke `/login`.

### 1.4 Tampilan umum

- [ ] **UI-COL-030** ✅ Ganti tema **Light / Dark / System** dari header. → Semua halaman collector terbaca: tabel, badge status, kartu statistik, grafik, dan modal.
- [ ] **UI-COL-031** ✅ Lebar < 992px. → Sidebar berubah menjadi drawer (tombol menu).
- [ ] **UI-COL-032** ✅ Lebar < 768px. → Kotak filter berubah menjadi tombol **Filter** yang membuka drawer dari bawah.
- [ ] **UI-COL-033** ✅ Matikan backend atau putuskan jaringan, lalu muat ulang sebuah tabel. → Tampil kotak error dengan tombol **Coba lagi**, bukan teks "Belum ada data".
- [ ] **UI-COL-034** ✅ Saat data dimuat, kartu statistik menampilkan skeleton (bukan sekadar teks "Loading…").

---

## 2. Data Kolektor — `/admin/collectors/list`

Menu: Manajemen Kolektor → **Data Kolektor**. Permission: `collector.read`.

### 2.1 Header & tabel

- [ ] **UI-COL-040** ✅ Judul **Data Kolektor**, subjudul "Kelola akun, profil, status kepegawaian, dan portofolio penagihan kolektor.", breadcrumb *Manajemen Kolektor / Data Kolektor*.
- [ ] **UI-COL-041** ✅ Tombol **Tambah Kolektor** tampil untuk admin_estate dan **tidak** tampil untuk supervisor/property_manager.
- [ ] **UI-COL-042** ✅ Kolom tabel: **Kode**, **Kolektor** (nama + foto/avatar), **Kontak**, **Status**, **Akun Kelolaan**, **Total Tunggakan**, **Menunggak / Kritis**, **Tertagih Bulan Ini**, **Aksi**.
- [ ] **UI-COL-043** ⛔ Nilai **Akun Kelolaan / Total Tunggakan / Menunggak / Kritis / Tertagih Bulan Ini** terisi angka nyata. *Saat ini semua 0/kosong karena backend belum mengirim `stats` (B-7).*
- [ ] **UI-COL-044** ✅ Pagination berfungsi (pindah halaman, ubah jumlah per halaman).
- [ ] **UI-COL-045** ✅ Tabel kosong karena filter. → Pesan kosong menyarankan mengubah filter.

### 2.2 Filter

- [ ] **UI-COL-050** ✅ Pencarian "Cari nama, username, kode kolektor" menyaring hasil (dengan jeda debounce).
- [ ] **UI-COL-051** ⚠️ Filter **Cluster wilayah tugas**: dropdown tampil. *Backend belum memproses filter `cluster_id` (B-7), jadi hasil tidak tersaring.*
- [ ] **UI-COL-052** ✅ Filter **Status Akun** (Aktif / Nonaktif / Cuti / Suspend) menyaring hasil.
- [ ] **UI-COL-053** ✅ Filter **Status Kepegawaian** (Tetap / Kontrak / Harian) menyaring hasil.
- [ ] **UI-COL-054** ⛔ supervisor hanya melihat collector di cluster-nya (`supervisor.rina` hanya Budi & Siti). *Saat ini supervisor melihat semua collector (B-7, scoping belum dipasang).*

### 2.3 Tambah kolektor (drawer)

- [ ] **UI-COL-060** ✅ Klik **Tambah Kolektor** → drawer **Tambah Kolektor** terbuka dengan tombol **Batal** dan **Simpan**.
- [ ] **UI-COL-061** ✅ Field yang ada: **Foto Profil**, **Nama Lengkap**, **Username**, **Password**, **Kode Kolektor**, **Email**, **Nomor Telepon**, **Nomor WhatsApp**, **Tanggal Bergabung**, **Jam Tugas Mulai**, **Jam Tugas Selesai**, **Status Kepegawaian**, **Status Akun**, **Alamat**, **Wilayah Kerja (catatan singkat)**, **Target Penagihan Bulanan Awal (Rp, opsional)**, **Catatan Admin**.
- [ ] **UI-COL-062** ✅ Simpan dengan Nama kosong → "Nama wajib diisi". Nama > 100 karakter → "Maksimal 100 karakter".
- [ ] **UI-COL-063** ✅ Username kosong → "Username wajib diisi". Username > 50 karakter → "Maksimal 50 karakter".
- [ ] **UI-COL-064** ✅ Password kosong saat tambah → "Password wajib diisi". Password < 8 karakter → "Minimal 8 karakter".
- [ ] **UI-COL-065** ✅ Email salah format → "Format email tidak valid".
- [ ] **UI-COL-066** ✅ Status Akun kosong saat tambah → "Status akun wajib dipilih".
- [ ] **UI-COL-067** ✅ Kode Kolektor dikosongkan → kode otomatis dibuat (format `COL-0001`). Tooltip field menjelaskan hal ini.
- [ ] **UI-COL-068** ✅ Username yang sudah dipakai → error validasi dari server tampil di field Username.
- [ ] **UI-COL-069** ⛔ Jam Tugas Mulai/Selesai (format HH:mm) **tersimpan** dan tampil lagi saat edit. *Saat ini backend mengabaikan kedua field ini (B-7).*
- [ ] **UI-COL-070** ✅ Jam Tugas Selesai lebih awal dari Jam Tugas Mulai → validasi menolak.
- [ ] **UI-COL-071** ✅ Isi Target Penagihan Bulanan Awal → setelah simpan, target bulan berjalan muncul di menu Target Kolektor.
- [ ] **UI-COL-072** ✅ Simpan berhasil → pesan "Kolektor berhasil ditambahkan.", drawer tertutup, tabel ter-refresh.

### 2.4 Foto profil

- [ ] **UI-COL-075** ✅ Unggah foto > 5 MB → "Ukuran foto maksimal 5 MB." (ditolak di browser).
- [ ] **UI-COL-076** ✅ Unggah foto valid → "Foto profil berhasil diunggah." dan pratinjau tampil.
- [ ] **UI-COL-077** ⛔ Buka Edit kolektor yang sudah punya foto → foto saat ini tampil. *Butuh `photo_url` dari backend (B-7).*

### 2.5 Edit kolektor

- [ ] **UI-COL-080** ✅ Menu **Aksi → Edit** membuka drawer **Edit Kolektor — {nama}** dengan data terisi.
- [ ] **UI-COL-081** ✅ Field Password boleh dikosongkan saat edit (password lama tetap).
- [ ] **UI-COL-082** ⛔ Simpan perubahan → "Data kolektor berhasil diperbarui." *Saat ini selalu gagal 422 "account_status wajib" (B-7).*
- [ ] **UI-COL-083** ⛔ Ganti username → bisa login dengan username baru, sesi lama collector berakhir. *Backend belum menyimpan username (B-7).*
- [ ] **UI-COL-084** ⛔ Edit hanya satu field → field lain (WhatsApp, alamat, catatan) tidak terhapus.

### 2.6 Ubah status & hapus (dengan serah terima penugasan)

- [ ] **UI-COL-090** ✅ **Aksi → Ubah Status / Nonaktifkan** membuka modal **Ubah Status — {nama}** dengan field **Status Akun** dan **Alasan / Catatan** (maks. 500 karakter).
- [ ] **UI-COL-091** ✅ Alert di modal menjelaskan bahwa status selain Aktif akan meminta pemindahan atau pengakhiran penugasan.
- [ ] **UI-COL-092** ✅ Ubah status collector **tanpa** penugasan aktif → "Status kolektor berhasil diperbarui."
- [ ] **UI-COL-093** ⛔ Nonaktifkan collector yang **masih punya** penugasan aktif → muncul peringatan "Kolektor masih memiliki penugasan aktif. Tentukan serah terima penugasan terlebih dahulu." lalu modal **Serah Terima Penugasan — {nama}** terbuka. *Backend belum mengembalikan 422 `active_assignment_count` (B-7).*
- [ ] **UI-COL-094** ⛔ Modal serah terima: pilihan **Pindahkan semua penugasan ke kolektor lain** / **Akhiri semua penugasan (unit menjadi belum ditugaskan)**.
- [ ] **UI-COL-095** ⛔ Pilih "Pindahkan" → field **Kolektor Tujuan** wajib ("Pilih kolektor tujuan") dan hanya berisi collector aktif selain dirinya. *Dropdown kosong (B-2).*
- [ ] **UI-COL-096** ⛔ **Alasan** wajib, 5–500 karakter (pesan "Alasan wajib diisi" / "minimal 5" / "maksimal 500").
- [ ] **UI-COL-097** ⛔ Tombol **Proses & Simpan Status** / **Proses & Hapus Kolektor** → penugasan berpindah atau berakhir. Cek di halaman Penugasan Kolektor.
- [ ] **UI-COL-098** ✅ **Aksi → Hapus** → konfirmasi **Hapus kolektor ini?** dengan tombol **Hapus**. Setelah dikonfirmasi: "Kolektor berhasil dihapus."
- [ ] **UI-COL-099** ✅ Menu aksi hanya menampilkan item sesuai permission (Edit: `collector.update`, Ubah Status: `collector.activate`, Hapus: `collector.delete`).

---

## 3. Detail Kolektor — `/admin/collectors/list/:id`

Dibuka dari **Aksi → Detail** di Data Kolektor. Permission: `collector.detail`.

- [ ] **UI-COL-110** ✅ Judul berisi nama collector; breadcrumb *Manajemen Kolektor / Data Kolektor / {nama}*. Breadcrumb "Data Kolektor" bisa diklik.
- [ ] **UI-COL-111** ✅ Saat memuat, tampil skeleton kartu statistik dan breadcrumb "Memuat…".
- [ ] **UI-COL-112** ⚠️ Kartu statistik: **Akun Kelolaan**, **Total Tunggakan**, **Akun Menunggak**, **Akun Prioritas Kritis**, **Tertagih Bulan Ini**, **Kunjungan Bulan Ini**, **Janji Bayar Bulan Ini**, **Penugasan Aktif**. *Saat ini hanya Total Tunggakan dan Penugasan Aktif yang terisi; sisanya "-" (B-7).*
- [ ] **UI-COL-113** ✅ Tab **Profil**: Nama, Username, Email, Telepon, WhatsApp, Alamat, Tanggal Bergabung, **Jam Tugas** ("Belum diatur" bila kosong), Status Kepegawaian, Status Akun (badge), Wilayah Kerja.
- [ ] **UI-COL-114** ✅ **Catatan Admin** tampil untuk admin.
- [ ] **UI-COL-115** ⛔ Login sebagai collector lalu buka detail dirinya: **Catatan Admin tidak boleh terlihat** (termasuk di response API). *Backend belum menyembunyikannya (B-7).*
- [ ] **UI-COL-116** ⛔ Tab **Portofolio Akun**: daftar akun kelolaan dengan kolom **Unit**, **Customer**, **Tunggakan**, **Umur**, **Status**, **Prioritas**, **Kontak Terakhir**, plus filter "Cari unit, nama, atau telepon", Status akun, Prioritas, dan "Hanya yang ada tunggakan / Semua akun". *Endpoint 501 (B-3).*
- [ ] **UI-COL-117** ✅ Tab **Wilayah & Penugasan ({n})**: tabel Penugasan Aktif dengan kolom **Cakupan**, **Detail**, **Prioritas**, **Mulai**, **Selesai** ("Tanpa batas"), **Asal Penugasan**, **Aksi**.
- [ ] **UI-COL-118** ✅ Tanpa penugasan aktif → "Kolektor ini belum memegang penugasan aktif. Tambahkan dari menu Penugasan Kolektor."
- [ ] **UI-COL-119** ✅ Kartu **Riwayat Semua Penugasan** dengan filter status dan kolom **Cakupan / Detail / Status / Periode / Asal Penugasan / Ditugaskan oleh**.
- [ ] **UI-COL-120** ⛔ Tombol **Pindahkan** → modal **Pindahkan Penugasan** dengan **Kolektor Tujuan** (wajib, collector aktif selain dirinya), **Alasan Pemindahan** (wajib, 5–500), **Mulai Berlaku**, dan **Catatan (opsional)**. *Dropdown kosong (B-2). Tanpa catatan → 500 (B-7).*
- [ ] **UI-COL-121** ⛔ Pindahkan berhasil → "Penugasan berhasil dipindahkan.", lalu kolom Asal Penugasan di penugasan baru menampilkan collector lama dan alasannya.
- [ ] **UI-COL-122** ✅ Tab **Target**: kolom **Periode**, **Mulai**, **Target Nominal**, **Target Kunjungan**, **Target Akun**, **Target Collection Rate**. Kosong → "Belum ada target untuk kolektor ini. Atur di menu Target Kolektor."
- [ ] **UI-COL-123** ✅ Tab **Aktivitas Terakhir**: kartu **Kunjungan Terakhir**, **Janji Bayar**, **Komplain**, masing-masing dengan pesan kosong yang sesuai.
- [ ] **UI-COL-124** ✅ Tab **Log Penugasan**: kolom **Waktu**, **Aktivitas**, **Aksi**, **Oleh**.
- [ ] **UI-COL-125** ✅ Tab **Lokasi Terakhir**: peta posisi terakhir, atau "Belum ada laporan lokasi. Lokasi muncul setelah kolektor mengirim lokasi dari aplikasi pada jam tugas."
- [ ] **UI-COL-126** ⛔ supervisor membuka detail collector di luar cluster-nya → ditolak (403). *Belum di-scope (B-7).*

---

## 4. Penugasan Kolektor — `/admin/collectors/assignments`

Menu: Manajemen Kolektor → **Penugasan Kolektor**. Permission: `collector-assignments.view`.

> ⛔ **Seluruh halaman ini terblokir oleh B-1** (gagal compile). Setelah B-1 diperbaiki, item tanpa catatan tambahan bisa langsung diuji.

### 4.1 Struktur halaman

- [ ] **UI-COL-140** ⛔ Judul **Penugasan Kolektor**, subjudul "Atur wilayah kerja setiap kolektor: cluster, blok, unit, atau penghuni tertentu."
- [ ] **UI-COL-141** ⛔ Tiga tab: **Daftar Penugasan**, **Tugaskan**, **Unit Belum Ditugaskan**. Tab aktif tersimpan di URL (`?tab=`), sehingga reload tetap di tab yang sama.

### 4.2 Tab Daftar Penugasan

- [ ] **UI-COL-145** ⛔ Kolom: **Kolektor**, **Cakupan**, **Periode**, **Prioritas**, **Status**, **Dipindahkan Dari** (tooltip berisi alasan), **Ditugaskan Oleh**, **Aksi**.
- [ ] **UI-COL-146** ⛔ Filter: "Cari unit / nama penghuni", Cluster/Blok/Unit, "Semua kolektor", "Jenis cakupan", "Status penugasan", dan tombol reset. *Backend baru memproses sebagian filter (B-7). Dropdown kolektor kosong (B-2).*
- [ ] **UI-COL-147** ⛔ Kosong → 'Belum ada penugasan. Buka tab "Tugaskan" untuk memberi wilayah kerja ke kolektor.'
- [ ] **UI-COL-148** ⛔ Tombol **Edit** nonaktif untuk penugasan *Dibatalkan/Dipindahkan* (tooltip "Penugasan yang dibatalkan/dipindahkan tidak dapat diedit").
- [ ] **UI-COL-149** ⛔ Tombol **Pindahkan** dan **Cabut** nonaktif untuk penugasan tidak aktif (tooltip sesuai).
- [ ] **UI-COL-150** ⛔ **Edit** → modal **Edit Penugasan Kolektor**: Kolektor dan Cakupan hanya tampil (tidak bisa diubah). Field yang bisa diubah: **Status**, **Prioritas**, **Tanggal Mulai** (wajib), **Tanggal Selesai** (harus ≥ mulai), **Catatan**. Simpan → "Penugasan kolektor berhasil diperbarui." *Saat ini 422 (B-7).*
- [ ] **UI-COL-151** ⛔ **Pindahkan** → modal **Pindahkan Penugasan**: Kolektor Tujuan (wajib, tanpa collector asal), **Alasan Pemindahan** (wajib, tidak boleh hanya spasi, 5–500), Tanggal Mulai (opsional, "Kosongkan untuk mulai hari ini."), Catatan. Berhasil → "Penugasan berhasil dipindahkan." *Saat ini 500 bila catatan kosong (B-7).*
- [ ] **UI-COL-152** ⛔ **Cabut** → konfirmasi **Cabut penugasan ini?**. Setelah dikonfirmasi: "Penugasan kolektor berhasil dicabut." dan status berubah menjadi Dibatalkan.

### 4.3 Tab Tugaskan

- [ ] **UI-COL-160** ⛔ Pilihan mode: **Manual**, **Massal**, **Area**.
- [ ] **UI-COL-161** ⛔ Mode **Manual**: **Kolektor** (wajib) dan **Jenis Cakupan** (wajib): *Seluruh Cluster / Blok Tertentu / Unit Tertentu / Penghuni Tertentu (mengikuti orangnya, bukan alamat)*. Field lanjutan menyesuaikan pilihan: Cluster, Blok, Unit, atau Penghuni ("Cari ID atau nama penghuni").
- [ ] **UI-COL-162** ⛔ Field umum: **Prioritas** (Rendah / Normal / Tinggi / Mendesak), **Tanggal Mulai** (placeholder "Hari ini"), **Tanggal Selesai** (opsional, ≥ mulai), **Catatan Penugasan**.
- [ ] **UI-COL-163** ⛔ Pilih **Blok Tertentu** → blok bisa dipilih tanpa error validasi (bug lama: blok terkirim sebagai array dan selalu ditolak).
- [ ] **UI-COL-164** ⛔ Simpan → "Penugasan kolektor berhasil dibuat."
- [ ] **UI-COL-165** ⛔ Buat penugasan yang sama persis untuk collector yang sama → ditolak dengan pesan duplikat. Untuk collector lain dengan cakupan identik → ditolak dengan pesan yang menyebut pemegangnya.
- [ ] **UI-COL-166** ⛔ Kartu **Ringkasan Cakupan** (pratinjau): **Unit tercakup**, **Belum ditugaskan**, **Total tunggakan**, **Akun menunggak**, **Akun kritis**, plus daftar collector yang sudah memegang unit di cakupan itu dan catatan "Penugasan yang lebih spesifik (unit > blok > cluster) akan diprioritaskan…". *501 (B-4).*
- [ ] **UI-COL-167** ⛔ Mode **Massal**: tabel **Pilih Unit** dengan sumber "Belum ditugaskan" / "Semua unit", filter cluster/blok, toggle hanya yang ada tunggakan, dan kolom **Unit / Alamat / Customer / Tunggakan / Status**.
- [ ] **UI-COL-168** ⛔ Massal tanpa unit dipilih → "Pilih minimal satu unit dari tabel." Lebih dari 500 unit → "Maksimal 500 unit per penugasan massal."
- [ ] **UI-COL-169** ⛔ Submit Massal → modal **Hasil Penugasan** dengan angka **Dibuat / Dipindahkan / Dilewati / Konflik**, tabel konflik (**Unit**, **Dipegang oleh**), dan kartu "Pindahkan unit konflik ke kolektor ini" (Alasan wajib 5–500).
- [ ] **UI-COL-170** ⛔ Mode **Area**: **Kolektor**, **Cluster** (wajib), **Blok (opsional)** dengan keterangan "Kosongkan untuk menugaskan seluruh cluster."

### 4.4 Tab Unit Belum Ditugaskan

- [ ] **UI-COL-175** ⛔ Kolom **Unit**, **Cluster**, **Blok / No**, **Customer**, **Tunggakan**, **Status**, **Prioritas**, **Aksi**.
- [ ] **UI-COL-176** ⛔ Filter cluster/blok/unit/customer/alamat, toggle hanya yang ada tunggakan, dan tombol **Reset**.
- [ ] **UI-COL-177** ⛔ Pilih beberapa unit lalu **Tugaskan** → pindah ke mode Massal dengan unit sudah terpilih.
- [ ] **UI-COL-178** ⛔ Unit yang baru saja ditugaskan hilang dari daftar ini.

---

## 5. Target Kolektor — `/admin/collectors/targets`

Menu: Manajemen Kolektor → **Target Kolektor**. Permission: `collector-targets.view`.

- [ ] **UI-COL-190** ✅ Judul **Target Kolektor**, subjudul "Pantau progres dan tetapkan target penagihan harian, mingguan, atau bulanan per kolektor."
- [ ] **UI-COL-191** ✅ Dua tab: **Progres** (default) dan **Daftar Target**.

### 5.1 Tab Progres

- [ ] **UI-COL-195** ⛔ Pilihan periode (Harian / Mingguan / Bulanan) dan pemilih tanggal yang menyesuaikan jenis periode. *Endpoint `progress` belum ada, jadi 500 (B-6).*
- [ ] **UI-COL-196** ⛔ Kartu **Kolektor**, **Total Tertagih**, **Mencapai Target Nominal**, **Rata-rata Collection Rate**.
- [ ] **UI-COL-197** ⛔ Tabel **Progres periode {label}**: kolom **Kolektor**, **Nominal Tertagih**, **Kunjungan**, **Akun Ditangani**, Collection Rate (tooltip "Perkiraan: tertagih ÷ (tertagih + tunggakan saat ini)."), dan **Aksi**, dengan progress bar target vs aktual.
- [ ] **UI-COL-198** ⛔ Toggle **Hanya kolektor tanpa target** dan pencarian "Cari nama / kode kolektor".
- [ ] **UI-COL-199** ⛔ Collector tanpa target menampilkan aksi untuk menetapkan target; collector bertarget menampilkan aksi ubah.
- [ ] **UI-COL-200** ⛔ Pencapaian > 100% ditampilkan apa adanya (mis. 180%), sementara bar berhenti di penuh.

### 5.2 Tab Daftar Target

- [ ] **UI-COL-205** ✅ Kolom **Kolektor**, **Periode**, **Mulai** (format tanggal, bukan ISO mentah), **Target Nominal**, **Target Kunjungan**, **Target Akun**, **Target Collection Rate**, **Aksi**.
- [ ] **UI-COL-206** ⚠️ Filter "Filter kolektor", "Jenis periode", "Awal periode". *Dropdown kolektor kosong (B-2). Filter awal periode belum diproses backend.*
- [ ] **UI-COL-207** ✅ Kosong → "Belum ada target untuk filter ini. Tambahkan target baru atau pilih periode lain."
- [ ] **UI-COL-208** ⛔ Tombol tambah target → modal **Tambah Target Kolektor**. *Field Kolektor kosong (B-2), jadi target baru tidak bisa dibuat dari UI.*
- [ ] **UI-COL-209** ✅ Validasi form: Kolektor wajib ("Pilih kolektor"), Jenis Periode wajib, Periode wajib, Target Nominal wajib ("Isi target nominal"), Target Collection Rate 0–100 ("Collection rate harus 0 – 100%").
- [ ] **UI-COL-210** ⛔ **Target Akun Ditangani** dan **Target Collection Rate** tersimpan. *Backend belum menyimpan kedua field ini.*
- [ ] **UI-COL-211** ⛔ Pilih tanggal di tengah minggu untuk periode Mingguan → tersimpan sebagai hari Senin minggu itu (normalisasi). Target ganda untuk periode yang sama → ditolak.
- [ ] **UI-COL-212** ✅ Ubah target (ikon pensil) → modal **Ubah Target Kolektor**. Simpan → "Target kolektor berhasil diperbarui."
- [ ] **UI-COL-213** ✅ Hapus (ikon tempat sampah) → konfirmasi **Hapus target ini?**, lalu "Target kolektor berhasil dihapus."
- [ ] **UI-COL-214** ✅ Tombol tambah/ubah/hapus hanya tampil bagi pemilik permission (`collector-targets.*` atau `collector.target_manage`).
- [ ] **UI-COL-215** ⛔ supervisor hanya bisa melihat dan mengubah target collector di cluster-nya.

---

## 6. Performa Kolektor — `/admin/collectors/performance` & `/collector/performance`

### 6.1 Tampilan admin/supervisor (Manajemen Kolektor → Performa Kolektor)

- [ ] **UI-COL-230** ✅ Judul **Performa Kolektor**, breadcrumb *Manajemen Kolektor / Performa*.
- [ ] **UI-COL-231** ⛔ Kartu ringkasan **Total Tertagih**, **Rata-rata Collection Rate**, **Total Kunjungan**, **PTP Terpenuhi**. *501 (B-5).*
- [ ] **UI-COL-232** ⛔ Pilihan metrik peringkat: **Nominal tertagih**, **Collection rate**, **Pencapaian target**, **Kunjungan**, **Kunjungan berhasil %**, **PTP terpenuhi**, **PTP fulfillment %**. Mengganti metrik mengubah urutan peringkat dan grafik.
- [ ] **UI-COL-233** ⛔ Grafik batang **{metrik} per kolektor**: warna terbaca di light dan dark mode.
- [ ] **UI-COL-234** ⛔ Tabel **Peringkat · {periode}**: kolom **#** (badge peringkat; 3 teratas ditonjolkan), **Kolektor**, **Nominal Tertagih**, **Collection Rate**, **Pencapaian Target**, **Kunjungan**, **Kunjungan Berhasil**, **PTP Terpenuhi**, **PTP Fulfillment**.
- [ ] **UI-COL-235** ⛔ Supervisor tanpa collector di cluster-nya → "Belum ada kolektor dalam cakupan Anda. Pastikan kolektor sudah ditugaskan ke cluster Anda."
- [ ] **UI-COL-236** ✅ Ganti jenis periode (Harian / Mingguan / Bulanan) → tanggal awal otomatis menyesuaikan awal periode (hari ini / Senin / tanggal 1).

### 6.2 Tampilan collector (menu Target & Performa)

- [ ] **UI-COL-240** ✅ Login sebagai collector → judul **Target & Performa Saya**.
- [ ] **UI-COL-241** ✅ Kartu **Terkumpul**, **Pencapaian Target**, **Jumlah Kunjungan** terisi untuk periode terpilih.
- [ ] **UI-COL-242** ⚠️ Kartu **Collection Rate**, **Kunjungan Berhasil**, **PTP Terpenuhi** belum terisi (backend belum mengirim metrik baru).
- [ ] **UI-COL-243** ✅ Angka **Terkumpul** dan **Pencapaian Target** sama dengan yang tampil di aplikasi Android collector untuk periode yang sama.
- [ ] **UI-COL-244** ✅ Collector tidak melihat pemilih kolektor lain maupun tabel peringkat.
- [ ] **UI-COL-245** ⛔ (Keamanan) Collector tidak bisa melihat performa collector lain, termasuk lewat manipulasi parameter `collector_id`. *Celah IDOR masih ada di backend (B-7).*

---

## 7. Monitoring Penagihan — `/collection/monitoring`

Menu: Manajemen Kolektor → **Monitoring Penagihan**. Permission: `collector-monitoring.view` (supervisor, admin_estate, property_manager).

> ⛔ Data halaman ini terblokir oleh B-3 (dan B-2 untuk filter kolektor). Tampilan kerangka, filter, dan state error tetap bisa dicek.

- [ ] **UI-COL-260** ✅ Judul **Monitoring Penagihan**, subjudul "Pantau akun penagihan, tunggakan, dan prioritas tindak lanjut lintas kolektor."
- [ ] **UI-COL-261** ✅ Saat endpoint gagal, tabel menampilkan error dengan **Coba lagi** (bukan tabel kosong).
- [ ] **UI-COL-262** ⛔ Kartu **Jumlah Akun**, **Total Tunggakan**, **Akun Kritis**, **Akun Menunggak**.
- [ ] **UI-COL-263** ⛔ Grafik **Umur Tunggakan** (Current / 1–30 / 31–60 / 61–90 / 91–180 / >180 hari) dengan toggle tabel (kolom **Umur / Akun / Tunggakan / Porsi**). Kosong → "Tidak ada tunggakan pada filter ini."
- [ ] **UI-COL-264** ⛔ Kartu **Distribusi Akun**: chip **Prioritas (klik untuk filter)** dan **Status (klik untuk filter)**. Klik chip menerapkan filter.
- [ ] **UI-COL-265** ✅ Filter yang tersedia: "Cari unit / customer / telepon", Cluster/Blok/Unit/Customer/Alamat, **Kolektor** (termasuk opsi **Belum ditugaskan**), **Status akun**, **Umur tunggakan**, **Prioritas**, **Tunggakan min.** / **Tunggakan maks.**
- [ ] **UI-COL-266** ⛔ Kombinasi beberapa filter menghasilkan data yang konsisten dengan kartu ringkasan.
- [ ] **UI-COL-267** ⛔ Kolom tabel: **Prioritas**, **Unit**, **Customer**, **Kolektor**, **Tunggakan**, **Jatuh Tempo Tertua**, **Umur**, **Kontak Terakhir**, **Tindak Lanjut**, **Status**.
- [ ] **UI-COL-268** ⛔ Pengurutan **Urutkan berdasarkan** (Prioritas / Tunggakan / Umur tunggakan / Jatuh tempo tertua / Kontak terakhir / Tindak lanjut berikut / Unit) dengan arah **Terbesar** / **Terkecil**.
- [ ] **UI-COL-269** ⛔ Lebar < 768px → daftar berubah menjadi kartu (tidak ada scroll horizontal berlebihan).
- [ ] **UI-COL-270** ⛔ Badge status akun (Current, Due Soon, Due Today, Overdue, Partially Paid, Promise to Pay, Disputed, Escalated, Paid) dan prioritas (Kritis / Tinggi / Sedang / Normal) berwarna konsisten dan tidak berlebihan.
- [ ] **UI-COL-271** ⛔ `supervisor.rina` hanya melihat akun di cluster Alamanda & Bougenville; admin_estate dan property_manager melihat semua.
- [ ] **UI-COL-272** ⛔ Kosong → "Tidak ada akun yang cocok dengan filter."

---

## 8. Lokasi Real-Time — `/admin/collectors/live-map`

- [ ] **UI-COL-290** ✅ Judul **Lokasi Real-Time Kolektor** dan peta tampil.
- [ ] **UI-COL-291** ✅ Kartu **Kolektor Aktif** berisi nama collector dan waktu lokasi terakhir.
- [ ] **UI-COL-292** ✅ Collector yang tidak mengirim lokasi (di luar jam tugas) tidak memiliki titik baru.
- [ ] **UI-COL-293** ⚠️ (Keamanan) supervisor hanya melihat collector di cluster-nya. *Saat ini tidak di-scope, jadi semua posisi terlihat.*

---

## 9. Surat Penagihan — `/admin/collectors/letters` & `/collector/letters`

- [ ] **UI-COL-300** ✅ Judul **Surat Penagihan**. Breadcrumb untuk admin: *Manajemen Kolektor / Surat Penagihan*; untuk collector: *Surat Penagihan*.
- [ ] **UI-COL-301** ✅ Kolom **Penghuni**, **Unit**, **Cluster**, **Jenis Surat**, **Dibuat Oleh**, **Tanggal**, **Aksi**; filter **Jenis Surat**.
- [ ] **UI-COL-302** ✅ Admin: tombol **Buat Surat** membuka modal **Buat Surat Penagihan** (Unit wajib, Jenis Surat wajib, Isi Surat wajib). Berhasil → "Surat penagihan berhasil dibuat."
- [ ] **UI-COL-303** ✅ Admin: tombol **Unduh** mengunduh PDF.
- [ ] **UI-COL-304** ✅ Collector: tombol **Buat Surat** dan **Unduh** **tidak** tampil (collector hanya punya permission lihat).
- [ ] **UI-COL-305** ⚠️ (Keamanan) Collector hanya melihat surat untuk unit miliknya. *Saat ini daftar surat tidak di-scope, sehingga collector melihat semua surat.*

---

## 10. Halaman Khusus Role Collector

### 10.1 Rute Kunjungan — `/collector/route`

- [ ] **UI-COL-320** ✅ Judul **Rute Kunjungan**, subjudul "Lokasi unit yang menjadi wilayah tugas Anda."
- [ ] **UI-COL-321** ✅ Peta hanya menampilkan unit milik collector yang login (bandingkan `kolektor.budi` dan `kolektor.ahmad`).
- [ ] **UI-COL-322** ✅ Kartu **Unit Tanpa Titik Koordinat** dengan tombol **Buka di Peta** (membuka Google Maps).

### 10.2 Pengingat WhatsApp — `/collector/reminders`

- [ ] **UI-COL-330** ✅ Judul **Pengingat WhatsApp**.
- [ ] **UI-COL-331** ✅ Field **Unit** ("Cari unit atau nama penghuni") hanya menampilkan unit milik collector.
- [ ] **UI-COL-332** ✅ **Nomor WhatsApp** wajib ("Nomor telepon wajib diisi"). Nomor tidak valid → "Nomor telepon penghuni tidak valid."
- [ ] **UI-COL-333** ✅ **Pesan** wajib ("Pesan wajib diisi").
- [ ] **UI-COL-334** ✅ Tombol kirim membuka WhatsApp (wa.me) dan mencatat pengingat: "Pengingat berhasil dicatat."
- [ ] **UI-COL-335** ⚠️ Kartu **Riwayat Pengingat — {unit}**: *saat ini gagal (403) karena endpoint riwayat mensyaratkan `reports.view` yang tidak dimiliki collector.*

### 10.3 Notifikasi, Profil, Ganti Password

- [ ] **UI-COL-340** ✅ Menu **Notifikasi** menampilkan notifikasi staf; tandai dibaca berfungsi.
- [ ] **UI-COL-341** ✅ **Profil** dan **Ganti Password** berfungsi untuk collector.

### 10.4 Fitur collector web yang belum ada (untuk dicatat, bukan diuji)

- [ ] **UI-COL-350** ⛔ Dashboard collector, My Collection, Detail akun + Timeline, Visits, Payments, Activities, Promise to Pay, Disputes, Escalations, Reports untuk collector: **belum dibuat** (rancangan §5.1, tahap T3–T10).

---

## 11. Visit & Janji Bayar di Detail Penghuni

Halaman Detail Resident → tab Kunjungan dan Janji Bayar (dipakai admin/staf).

- [ ] **UI-COL-360** ✅ Tab kunjungan menampilkan riwayat kunjungan collector untuk penghuni tersebut.
- [ ] **UI-COL-361** ✅ Tambah/ubah kunjungan lewat drawer (hanya untuk pemilik permission `visits.create` / `visits.update`).
- [ ] **UI-COL-362** ✅ Menandai kunjungan "completed" tanpa tanda tangan → ditolak dengan pesan dari server.
- [ ] **UI-COL-363** ✅ Tab janji bayar: tambah/ubah PTP (permission `payment-promises.*`).
- [ ] **UI-COL-364** ✅ Setelah visit/PTP dibuat, akun terkait muncul di timeline (dicek di DB/API sampai UI timeline tersedia).

---

## 12. Verifikasi Pembayaran (Finance)

- [ ] **UI-COL-370** ✅ Halaman Pembayaran: transaksi transfer manual dengan status *Menunggu Verifikasi* bisa di-**Verify** / **Reject** (Reject wajib alasan) oleh role dengan `payments.verify`.
- [ ] **UI-COL-371** ⛔ Antrean verifikasi khusus pembayaran yang dikumpulkan collector (Approve / Reject / Request Revision): **belum ada**. Pembayaran collector saat ini langsung berstatus lunas (keputusan D1 belum diimplementasikan).

---

## 13. Halaman Supervisor

### 13.1 Dashboard Supervisor — `/supervisor/dashboard`

- [ ] **UI-COL-380** ✅ Judul **Dashboard Supervisor**.
- [ ] **UI-COL-381** ✅ Kartu: **Kolektor Aktif**, **Kolektor Nonaktif**, **Sedang Bertugas Hari Ini**, **Tidak Mengirim Lokasi**, **Target Penghuni**, **Unit Belum Bayar**, **Total Piutang**, **Pembayaran Sukses Hari Ini**, **Pembayaran On-Site Hari Ini**, **PTP Aktif**, **Broken Promise**, **Komplain Terbuka**, **Kondisi Darurat Aktif**, **Kunjungan Hari Ini**, **Persetujuan Menunggu**.
- [ ] **UI-COL-382** ✅ Angka hanya mencakup cluster supervisor yang login (bandingkan `supervisor.rina` dan `supervisor.hendra`).
- [ ] **UI-COL-383** ⚠️ **Kondisi Darurat Aktif** tidak menghitung SOS dari collector (SOS dikirim tanpa unit, sedangkan dashboard memfilter per unit).

### 13.2 Monitoring Kolektor — `/supervisor/collectors`

- [ ] **UI-COL-390** ✅ Judul **Monitoring Kolektor**; filter "Cari nama kolektor" dan **Status Akun**.
- [ ] **UI-COL-391** ✅ Kolom **Kode**, **Nama**, **Status Akun**, **Lokasi Terakhir** (atau tag "Belum…"), **Kunjungan Hari Ini**, **PTP Aktif**, **Aksi → Detail**.
- [ ] **UI-COL-392** ✅ Hanya collector di cluster supervisor yang tampil.
- [ ] **UI-COL-393** ✅ Detail: kartu **Total Unit**, **Total Tunggakan**, **Pembayaran Terkumpul**, **Broken Promise**, plus tab **Penugasan**, **Kunjungan Terbaru**, **Janji Bayar (PTP)**, dan komplain.
- [ ] **UI-COL-394** ✅ Membuka detail collector di luar cluster lewat URL → ditolak.

### 13.3 Halaman supervisor lainnya

- [ ] **UI-COL-400** ✅ **Pusat Persetujuan**: tombol **Ajukan Cicilan** dan **Ajukan Penyesuaian Tagihan**; filter **Jenis Pengajuan** dan **Status**; kolom **No. Pengajuan / Jenis / Pemohon / Nominal / Status / Diajukan / Aksi**; aksi **Detail**, Setujui, Tolak.
- [ ] **UI-COL-401** ✅ **Analisis Tunggakan per Cluster**: kartu **Peta Intensitas Tunggakan**; kolom **Cluster / Unit Aktif / Unit Menunggak / % Menunggak / Total Tunggakan / Broken Promise / Pelanggaran / Skor Prioritas**; hanya cluster milik supervisor.
- [ ] **UI-COL-402** ✅ **Peta Pemantauan Wilayah**: kartu **Kolektor Aktif** dan **Kondisi Darurat Aktif**; data diperbarui berkala (± 60 detik).
- [ ] **UI-COL-403** ✅ **Pusat Notifikasi & Eskalasi**: filter **Prioritas** dan **Status Penanganan**; kolom **Prioritas / Kategori / Judul / Status / Dibuat / Aksi**; aksi **Tandai Dibaca**, **Selesai**, dan eskalasi (modal **Eskalasi Notifikasi**).
- [ ] **UI-COL-404** ✅ **Broadcast & Pengumuman**: form **Kirim Broadcast Baru** dan tabel **Riwayat Broadcast** (**Jenis / Pesan / Pengirim / Penerima / Berhasil / Gagal / Waktu**).
- [ ] **UI-COL-405** ✅ **Laporan & Ekspor**: form **Minta Laporan Baru** (jenis Performa Kolektor, periode Harian/Mingguan/Bulanan, tombol **Buat Laporan**). Tabel **Riwayat Ekspor** ter-refresh otomatis; tombol **Unduh** menghasilkan CSV.

### 13.4 Manajemen Supervisor (admin)

- [ ] **UI-COL-410** ✅ **Data Supervisor**: tambah, edit, ubah status, hapus, dan unggah foto supervisor.
- [ ] **UI-COL-411** ⚠️ Edit username supervisor → *belum tersimpan di backend* (bug yang sama dengan kolektor).
- [ ] **UI-COL-412** ✅ **Penugasan Wilayah**: assign/reassign supervisor ke cluster. Perubahan langsung memengaruhi data yang dilihat supervisor itu.

---

## 14. Pengecekan Keamanan Lintas Halaman (Ringkas)

| ID | Status | Cek | Diharapkan |
|---|---|---|---|
| UI-COL-420 | ✅ | Collector membuka URL halaman admin collector | `/403` |
| UI-COL-421 | ⛔ | Collector melihat performa collector lain | Ditolak (saat ini bocor) |
| UI-COL-422 | ⚠️ | Collector melihat semua surat penagihan | Hanya unit miliknya (saat ini bocor) |
| UI-COL-423 | ⚠️ | Collector / supervisor melihat lokasi semua collector | Collector: tidak boleh; supervisor: hanya cluster-nya (saat ini bocor) |
| UI-COL-424 | ⛔ | Supervisor melihat / mengubah collector, penugasan, atau target di luar cluster | Ditolak (saat ini belum di-scope) |
| UI-COL-425 | ⛔ | Collector melihat Catatan Admin miliknya | Tidak terlihat |
| UI-COL-426 | ✅ | Akun dinonaktifkan saat sedang login | Request berikutnya ditolak dan sesi berakhir |
| UI-COL-427 | ✅ | property_manager mencoba aksi tulis | Tombol tulis tidak tampil |

---

## 15. Ringkasan Status

| Area | ✅ Bisa dicek | ⚠️ Sebagian | ⛔ Terblokir |
|---|---|---|---|
| Navigasi & shell | Mayoritas | Menu ganda collector, landing collector | — |
| Data Kolektor | Tabel, filter dasar, tambah, foto, hapus | Filter cluster | Statistik, edit, serah terima, scoping |
| Detail Kolektor | Profil, penugasan aktif, target, aktivitas, log, lokasi | Kartu statistik | Portofolio, pindahkan, scoping |
| Penugasan Kolektor | — | — | **Seluruh halaman** (B-1, lalu B-2/B-4/B-7) |
| Target Kolektor | Daftar, ubah, hapus, validasi form | Filter | Tab Progres, tambah baru, field baru |
| Performa | Tampilan collector | Metrik tambahan collector | Peringkat admin/supervisor |
| Monitoring Penagihan | Kerangka, filter, state error | — | Seluruh data |
| Halaman collector (rute, pengingat, surat) | Mayoritas | Riwayat pengingat, scoping surat | Fitur T3–T10 |
| Supervisor | Mayoritas | Darurat dari collector, username supervisor | — |

> Setelah penghalang **B-1 s/d B-7** diselesaikan (tahap backend B1–B4), perbarui status item di dokumen ini lalu jalankan ulang seluruh checklist.
