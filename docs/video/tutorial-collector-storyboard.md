# Tutorial Video — Modul Collector (Penugasan & Seluruh Fitur Kolektor)

Video: `tutorial-collector.mp4` (1440×900, H.264 + AAC, narasi bahasa Indonesia, caption tertanam, durasi ±11:09).
Direkam dari aplikasi yang berjalan (frontend React + API Laravel) dengan data demo seeder pada database SQLite lokal terpisah (bukan database produksi). Akun yang dipakai: **Admin Estate** (`admin.estate`), **Supervisor** (`supervisor.rina`, cluster Alamanda & Bougenville), dan **Kolektor** (`kolektor.budi`).

## 1. Hasil analisis fitur Collector

| Area | Yang tersedia di aplikasi |
|---|---|
| Akses | Grup menu **Manajemen Kolektor** tampil sesuai izin: Admin Estate dan Super Admin melihat semua submenu, Property Manager sebagian (tanpa Lokasi Real-Time dan Surat Penagihan), Supervisor juga melihatnya dengan data terbatas pada cluster-nya. Grup ini disembunyikan bagi akun yang hanya berperan kolektor. Supervisor juga punya grup menu **Supervisor**. Kolektor mendapat menu khusus Target & Performa, Rute Kunjungan, Pengingat WhatsApp, Surat Penagihan, di samping menu umum (Dashboard, Cluster, Unit Properti, Tagihan, Pembayaran). |
| Data Kolektor (`/admin/collectors/list`) | Tabel: kode, kolektor, kontak, status akun (Aktif/Nonaktif/Cuti/Suspend), status kepegawaian, akun kelolaan & penugasan aktif, total tunggakan, akun menunggak & kritis, tertagih bulan ini. Pencarian nama/username/kode; filter cluster wilayah tugas, status akun, status kepegawaian. |
| Tambah / Edit Kolektor | Nama*, username*, password* (min. 8), kode kolektor (otomatis bila kosong), email, telepon, WhatsApp, tanggal bergabung, jam tugas mulai/selesai, status kepegawaian, status akun*, alamat, wilayah kerja (catatan), target penagihan bulanan awal (opsional, hanya saat tambah), catatan admin. Mengganti username mencabut semua token login lama (kolektor keluar dari semua perangkat). Mengganti password hanya menolak login baru dengan password lama; sesi yang sedang aktif tetap berlaku sampai token kedaluwarsa (maks. 8 jam). |
| Ubah Status & Serah Terima | Mengubah status menjadi selain Aktif saat kolektor masih memegang penugasan memunculkan **Serah Terima Penugasan**: pindahkan semua penugasan ke kolektor aktif lain atau akhiri semuanya; alasan wajib dan tercatat di audit log. |
| Detail Kolektor | Kartu ringkasan (akun kelolaan, total tunggakan, akun menunggak, akun prioritas kritis, tertagih bulan ini vs target, kunjungan, janji bayar, penugasan aktif); tab Profil, Portofolio Akun, Wilayah & Penugasan, Target, Aktivitas Terakhir, Log Penugasan, Lokasi Terakhir. |
| Penugasan — Tugaskan (`/admin/collectors/assignments`) | Mode **Manual** (seluruh cluster / blok / unit / penghuni), **Area** (cluster atau satu blok), **Massal** (hingga 500 unit dari tabel; bawaan unit belum ditugaskan). Kartu **Ringkasan Cakupan** sebelum menyimpan; prioritas, tanggal mulai/selesai, catatan. Cakupan yang sama persis dengan periode tumpang tindih ditolak di Manual dan Area, baik bila dipegang kolektor yang sama maupun kolektor lain (pesan mengarahkan ke Pindahkan). Di Massal, modal **Hasil Penugasan** menampilkan unit yang dibuat, dilewati (sudah ditugaskan per unit ke kolektor ini), dan konflik (sudah ditugaskan per unit ke kolektor lain); unit konflik bisa langsung dipindahkan dari modal dengan alasan wajib. |
| Penugasan — Daftar | Filter unit/penghuni, cluster, blok, unit, kolektor, jenis cakupan, status, hanya yang berlaku hari ini. **Edit** hanya jadwal, prioritas, status, catatan (kolektor & cakupan tidak dapat diubah). **Pindahkan** ke kolektor lain dengan alasan wajib → penugasan lama berstatus *Dipindahkan*, kolom **Dipindahkan Dari** + tooltip alasan. **Cabut** dengan konfirmasi → status *Dibatalkan*. |
| Unit Belum Ditugaskan | Unit tanpa kolektor; filter cluster, blok, unit, customer, alamat, hanya yang menunggak; **Tugaskan** per baris atau **Tugaskan Terpilih** membawa unit ke mode Massal. |
| Target Kolektor (`/admin/collectors/targets`) | Periode harian / mingguan / bulanan. Tab **Progres**: nominal tertagih, kunjungan, akun ditangani, collection rate terhadap target; filter cluster, pencarian, *Hanya tanpa target*; aksi **Set target** / **Ubah**. Tab **Daftar Target**. Target ganda untuk kolektor dan periode yang sama ditolak. |
| Performa Kolektor (`/admin/collectors/performance`) | Ringkasan total tertagih, rata-rata collection rate, total kunjungan, PTP terpenuhi. Peringkat berdasarkan nominal tertagih, collection rate, pencapaian target, kunjungan, kunjungan berhasil %, PTP terpenuhi, PTP fulfillment %; grafik + tabel, tiga teratas ditandai. |
| Monitoring Penagihan (`/collection/monitoring`) | Kartu jumlah akun, total tunggakan, akun kritis, akun menunggak; grafik/tabel umur tunggakan; chip distribusi prioritas & status (klik untuk memfilter); filter cari, cluster, blok, unit, customer, alamat, kolektor (termasuk **Belum ditugaskan**), status, umur tunggakan, prioritas, rentang tunggakan, termasuk akun lunas; urutan prioritas / tunggakan / umur / jatuh tempo / kontak terakhir. |
| Lokasi Real-Time | Peta posisi terakhir yang dilaporkan setiap kolektor dalam cakupan pengguna (supervisor: kolektor yang punya penugasan aktif di cluster-nya), plus daftar nama, waktu laporan terakhir, dan akurasi; dimuat ulang tiap 60 detik. Server hanya menerima ping lokasi pada jam tugas kolektor (bawaan 07:00–18:00). |
| Surat Penagihan | Daftar surat (penghuni, unit, cluster, jenis, dibuat oleh, tanggal), filter, **Buat Surat**, **Unduh** PDF. Kolektor hanya melihat surat untuk unit yang ia pegang. |
| Supervisor | Dashboard Supervisor, Monitoring Kolektor (status akun, waktu laporan lokasi terakhir, kunjungan hari ini, PTP aktif), Monitoring Penagihan, Analisis Tunggakan, Peta Pemantauan, dan modul kolektor dibatasi ke cluster yang diawasi. **Notifikasi & Eskalasi**: dibuat otomatis tiap jam (mis. PTP jatuh tempo, broken promise); bisa ditandai selesai, atau dieskalasi ke pengguna yang ID-nya diisi manual. |
| Kolektor (web) | Target & Performa Saya, Rute Kunjungan (peta + unit tanpa koordinat → Google Maps), Pengingat WhatsApp (tautan wa.me + riwayat pengiriman), Surat Penagihan. Daftar cluster, unit, penghuni, kuitansi, surat, pengingat, dan rute dibatasi ke unit yang ditugaskan. |

**Catatan temuan saat pembuatan video**

Diperbaiki saat pembuatan video:
- Tanggal penugasan tampil mundur satu hari, dan form **Edit** menyimpan ulang tanggal yang sudah mundur itu. Penyebabnya, API mengirim tanggal sebagai datetime UTC (mis. `2026-10-07T17:00:00Z` untuk 8 Okt WIB). Model `CollectorAssignment` kini menyerialisasi tanggal sebagai `Y-m-d`, dan sudah ada test regresinya.
- Label cakupan ganda "Cluster Cluster Alamanda" (detail kolektor) dan "Seluruh cluster Cluster Flamboyan" (daftar penugasan).

Belum diperbaiki (perlu keputusan), hasil pemeriksaan kode:
- **Cakupan data kolektor:** Dashboard umum (halaman awal setelah login, `ReportController`) dan halaman **Tagihan** (`BillingController`) menampilkan data seluruh estate. Halaman detail penghuni (`/residents/:id`, `ResidentDetailController`) bisa dibuka lewat URL tanpa pemeriksaan penugasan.
- **Cakupan data supervisor:** Dashboard umum, menu Cluster/Unit/Tagihan/Pembayaran/Piutang/Laporan, **Pusat Persetujuan**, riwayat **Broadcast**, serta notifikasi PTP jatuh tempo dan broken promise masih menampilkan data seluruh estate. Yang dibatasi ke cluster-nya hanya modul supervisor dan modul penagihan.
- **Lokasi:** aplikasi Android tidak memeriksa jam tugas. Selama aplikasi terbuka, aplikasi tetap mengirim lokasi tiap 5 menit, dan ping di luar jam tugas ditolak server tanpa pemberitahuan. Peta Lokasi Real-Time tidak menyaring kolektor yang sedang bertugas, jadi posisi lama dan kolektor nonaktif tetap tampil.
- **Eskalasi notifikasi:** tidak ada konsep atasan. Penerima diisi manual lewat ID user, dan penerima tidak mendapat pemberitahuan.
- **Sesi kolektor:** mengganti password tidak mencabut sesi yang sedang aktif. Token hanya dicabut saat username diganti atau akun dinonaktifkan/dihapus.
- **Penugasan lintas level** (mis. unit di dalam cluster milik kolektor lain) tidak ditolak maupun dilaporkan. Yang ada hanya peringatan di Ringkasan Cakupan. Teks peringatan itu menyebut urutan "unit > blok > cluster", padahal urutan sebenarnya unit > penghuni > blok > cluster.

Catatan rekaman:
- Popup wa.me dicegat karena browser perekam tidak dapat membuka WhatsApp. Pada pemakaian nyata, WhatsApp terbuka di tab baru.
- Fitur lapangan (kunjungan dengan foto & GPS, janji bayar, pengumpulan pembayaran) ada di aplikasi Android dan tidak dibahas di video ini.

## 2. Skenario yang direkam (semua benar-benar dijalankan)

**Login Admin Estate → Data Kolektor (cari, tambah *Rina Kusuma* → COL-0008, detail *Budi Santoso*) → Penugasan Manual (*Dewi* → Cluster Flamboyan) → Area (*Ahmad* → Cluster Harmoni) → Massal (*Siti* → 4 unit Gardenia) → Edit prioritas → Pindahkan Flamboyan ke *Ahmad* dengan alasan → Cabut Harmoni → Unit Belum Ditugaskan → Tugaskan (mode Massal) → Nonaktifkan *Dewi* + serah terima ke *Siti* → Set target *Rina* → Performa → Monitoring Penagihan (chip status, urutan, belum ditugaskan) → Lokasi Real-Time → Surat Penagihan → Login Supervisor (dashboard, monitoring kolektor, monitoring penagihan terbatas cluster, notifikasi) → Login Kolektor (target saya, rute, pengingat WhatsApp tercatat, surat)**

## 3. Storyboard

### Scene 1 — Pembukaan & Tiga Peran  `00:00`

| | |
|---|---|
| **Tampilan / menu** | Kartu judul *Modul Collector* lalu *Tiga Peran* |
| **Aksi user** | Tidak ada interaksi; pengantar materi |
| **UI yang disorot** | Cakupan video dan tiga peran: Admin Estate, Supervisor, Kolektor |
| **Transisi** | Ke halaman Login |

**Narasi**

- `00:00` Selamat datang di tutorial modul Collector. Video ini membahas seluruh alur kerja penagihan: mengelola data kolektor, menugaskan wilayah, menetapkan target, memantau penagihan, sampai tampilan untuk supervisor dan kolektor.
- `00:17` Ada tiga peran utama. Admin estate mengelola kolektor dan penugasan. Supervisor memantau kolektor di cluster yang menjadi tanggung jawabnya. Kolektor bekerja di lapangan menangani unit yang ditugaskan kepadanya.

### Scene 2 — Login Admin Estate & Menu Manajemen Kolektor  `00:33`

| | |
|---|---|
| **Tampilan / menu** | Login → Dashboard → sidebar |
| **Aksi user** | Login sebagai `admin.estate`, buka grup menu **Manajemen Kolektor** |
| **UI yang disorot** | Submenu Monitoring Penagihan, Data Kolektor, Penugasan Kolektor, Target Kolektor, Performa Kolektor, Lokasi Real-Time, Surat Penagihan |
| **Transisi** | Kartu bab 1 |

**Narasi**

- `00:33` Kita mulai sebagai admin estate. Masukkan username dan password, lalu klik Masuk.
- `00:42` Semua fitur pengelolaan ada di grup menu Manajemen Kolektor: Monitoring Penagihan, Data Kolektor, Penugasan Kolektor, Target Kolektor, Performa Kolektor, Lokasi Real-Time, dan Surat Penagihan.

### Scene 3 — Data Kolektor  `00:58`

| | |
|---|---|
| **Tampilan / menu** | `/admin/collectors/list` → drawer **Tambah Kolektor** → Detail Kolektor *Budi Santoso* |
| **Aksi user** | Cari "siti"; tambah kolektor *Rina Kusuma* (`kolektor.rina`, WA 081299990001, jam tugas 08:00–17:00, status Aktif, target awal dikosongkan); buka detail Budi, tab **Portofolio Akun** dan **Wilayah & Penugasan** |
| **UI yang disorot** | Kolom status akun, akun kelolaan, total tunggakan, tertagih bulan ini; kode kolektor otomatis (COL-0008); kartu ringkasan kinerja di halaman detail |
| **Transisi** | Kartu bab 2 |

**Narasi**

- `00:58` Bagian satu: Data Kolektor.
- `01:01` Halaman Data Kolektor menampilkan setiap kolektor beserta status akun, jumlah akun kelolaan, total tunggakan, akun menunggak dan kritis, serta nominal yang tertagih bulan ini.
- `01:15` Gunakan kolom pencarian dan filter status untuk menemukan kolektor dengan cepat.
- `01:21` Untuk menambah kolektor baru, klik Tambah Kolektor. Isi nama lengkap, username, dan password minimal delapan karakter. Kode kolektor boleh dikosongkan, karena sistem membuatnya otomatis.
- `01:35` Lengkapi nomor WhatsApp, jam tugas, status akun, dan bila perlu target penagihan bulanan awal. Jam tugas dipakai untuk membatasi pelacakan lokasi di luar jam kerja.
- `01:49` Klik Simpan. Kolektor baru langsung muncul di tabel dengan kode otomatis.
- `01:54` Klik nama kolektor untuk membuka halaman detail. Di sini ada ringkasan kinerja, profil, portofolio akun, wilayah penugasan, target, aktivitas terakhir, log penugasan, dan lokasi terakhir.
- `02:10` Tab Portofolio Akun menampilkan semua akun yang dipegang kolektor ini, lengkap dengan tunggakan, umur tunggakan, status, prioritas, dan kontak terakhir.
- `02:22` Tab Wilayah dan Penugasan menunjukkan cakupan kerja yang sedang aktif, beserta riwayat seluruh penugasannya.

### Scene 4 — Penugasan Manual  `02:30`

| | |
|---|---|
| **Tampilan / menu** | `/admin/collectors/assignments` → tab **Tugaskan**, mode **Manual** |
| **Aksi user** | Pilih *Dewi Lestari* → Seluruh Cluster → *Cluster Flamboyan*, isi catatan, **Simpan Penugasan** |
| **UI yang disorot** | Kartu **Ringkasan Cakupan** (unit tercakup, belum ditugaskan, total tunggakan, kolektor saat ini); toast sukses |
| **Transisi** | Ganti mode ke Area |

**Narasi**

- `02:30` Bagian dua: Penugasan Kolektor. Inilah inti pengaturan wilayah kerja.
- `02:36` Buka menu Penugasan Kolektor. Ada tiga tab: Daftar Penugasan, Tugaskan, dan Unit Belum Ditugaskan. Kita mulai dari tab Tugaskan.
- `02:47` Mode Manual dipakai untuk satu cakupan. Pilih kolektor, lalu jenis cakupan: seluruh cluster, blok tertentu, unit tertentu, atau penghuni tertentu. Di sini kita menugaskan Dewi ke seluruh Cluster Flamboyan.
- `03:04` Kartu Ringkasan Cakupan di sebelah kanan langsung menampilkan jumlah unit, unit yang belum ditugaskan, total tunggakan, dan kolektor lain yang sudah memegang wilayah itu. Periksa ringkasan ini sebelum menyimpan.
- `03:20` Atur prioritas dan catatan bila perlu, lalu klik Simpan Penugasan.

### Scene 5 — Penugasan Area & Massal  `03:26`

| | |
|---|---|
| **Tampilan / menu** | Tab **Tugaskan**, mode **Area** lalu **Massal** |
| **Aksi user** | Area: *Ahmad Fauzi* → *Cluster Harmoni* (blok dikosongkan = seluruh cluster). Massal: *Siti Rahmawati* → filter *Cluster Gardenia* → centang 4 unit → **Tugaskan 4 Unit** |
| **UI yang disorot** | Modal **Hasil Penugasan** (dibuat / dipindahkan / dilewati / konflik); chip unit terpilih (maks. 500) |
| **Transisi** | Ke tab Daftar Penugasan |

**Narasi**

- `03:26` Mode Area adalah cara cepat menugaskan satu cluster atau satu blok. Pilih kolektor dan cluster. Kosongkan blok untuk menugaskan seluruh cluster.
- `03:39` Mode Massal dipakai untuk banyak unit sekaligus, hingga lima ratus unit. Pilih kolektor, lalu centang unit dari tabel. Secara bawaan tabel menampilkan unit yang belum ditugaskan, dan bisa difilter per cluster.
- `03:55` Ringkasan cakupan ikut diperbarui sesuai unit yang dipilih. Klik tombol tugaskan, lalu sistem menampilkan hasilnya: berapa yang dibuat, dilewati, dipindahkan, atau bentrok dengan kolektor lain.

### Scene 6 — Daftar Penugasan: Edit, Pindahkan, Cabut  `04:10`

| | |
|---|---|
| **Tampilan / menu** | Tab **Daftar Penugasan** |
| **Aksi user** | Filter kolektor *Dewi*; **Edit** prioritas Flamboyan → Tinggi; **Pindahkan** Flamboyan ke *Ahmad* dengan alasan wajib; filter *Ahmad* dan arahkan kursor ke kolom **Dipindahkan Dari**; **Cabut** penugasan Harmoni |
| **UI yang disorot** | Edit hanya mengubah jadwal/prioritas/status/catatan; status lama *Dipindahkan*; tooltip alasan pemindahan; status *Dibatalkan* setelah dicabut |
| **Transisi** | Ke tab Unit Belum Ditugaskan |

**Narasi**

- `04:10` Kembali ke tab Daftar Penugasan. Semua penugasan tampil di sini dan bisa difilter per cluster, kolektor, jenis cakupan, dan status.
- `04:21` Tombol Edit hanya mengubah jadwal, prioritas, status, dan catatan. Kolektor dan cakupan tidak bisa diubah di sini. Contohnya, kita naikkan prioritas Cluster Flamboyan menjadi tinggi.
- `04:36` Untuk mengganti kolektor, gunakan tombol Pindahkan. Pilih kolektor tujuan dan wajib isi alasan. Penugasan lama ditandai dipindahkan, dan alasannya tercatat di log audit.
- `04:49` Penugasan baru milik Ahmad menampilkan asal pemindahannya di kolom Dipindahkan Dari. Arahkan kursor ke kolom itu untuk melihat alasannya.
- `05:00` Penugasan yang tidak diperlukan lagi bisa dicabut dengan tombol Cabut, lalu konfirmasi. Unit di wilayah itu kembali menjadi belum ditugaskan.

### Scene 7 — Unit Belum Ditugaskan  `05:11`

| | |
|---|---|
| **Tampilan / menu** | Tab **Unit Belum Ditugaskan** |
| **Aksi user** | Aktifkan *Hanya yang menunggak*, klik **Tugaskan** pada baris pertama (NI001) |
| **UI yang disorot** | Unit dibawa ke mode Massal dengan unit sudah terpilih |
| **Transisi** | Kartu bab 3 |

**Narasi**

- `05:11` Tab Unit Belum Ditugaskan membantu memastikan tidak ada unit yang terlewat. Filter per cluster atau hanya yang ada tunggakan, lalu klik Tugaskan untuk langsung membawa unit itu ke mode Massal.

### Scene 8 — Serah Terima saat Menonaktifkan Kolektor  `05:25`

| | |
|---|---|
| **Tampilan / menu** | Data Kolektor → menu **Aksi** *Dewi Lestari* → **Ubah Status** → modal **Serah Terima Penugasan** |
| **Aksi user** | Status *Nonaktif* + alasan "Kontrak berakhir"; pindahkan semua penugasan ke *Siti Rahmawati* dengan alasan; **Proses & Simpan Status** |
| **UI yang disorot** | Peringatan jumlah penugasan aktif; pilihan pindahkan / akhiri; Dewi jadi Nonaktif dengan 0 penugasan, penugasan Siti bertambah |
| **Transisi** | Kartu bab 4 |

**Narasi**

- `05:25` Bagian tiga: menonaktifkan kolektor dengan serah terima.
- `05:30` Bila kolektor cuti, berhenti, atau dinonaktifkan sementara masih memegang wilayah, sistem tidak membiarkan wilayah itu terbengkalai. Buka menu Aksi, lalu pilih Ubah Status.
- `05:44` Pilih status Nonaktif, isi alasan, lalu simpan.
- `05:51` Karena Dewi masih memegang penugasan aktif, muncul jendela Serah Terima Penugasan. Pilih apakah semua penugasan dipindahkan ke kolektor lain atau diakhiri. Kita pindahkan ke Siti, dengan alasan yang wajib diisi.
- `06:08` Klik Proses dan Simpan Status. Status Dewi berubah, seluruh wilayahnya berpindah ke Siti, dan sesi Dewi di aplikasi langsung berakhir.

### Scene 9 — Target Kolektor  `06:18`

| | |
|---|---|
| **Tampilan / menu** | `/admin/collectors/targets` → tab **Progres** (Bulanan, Oktober 2026) |
| **Aksi user** | Aktifkan *Hanya tanpa target*; **Set target** untuk *Rina Kusuma* (Rp 7.500.000, 40 kunjungan); **Simpan** |
| **UI yang disorot** | Ringkasan progres; kolektor dan periode terisi otomatis dari baris; progres Rina (Rp 0 / Rp 7.500.000) |
| **Transisi** | Ke Performa Kolektor |

**Narasi**

- `06:18` Bagian empat: Target dan Performa.
- `06:22` Menu Target Kolektor dibuka di tab Progres. Pilih jenis periode, dan setiap kolektor menampilkan progres nominal tertagih, kunjungan, akun yang ditangani, serta collection rate terhadap targetnya.
- `06:37` Kolektor yang belum punya target bisa langsung diberi target. Aktifkan Hanya tanpa target, lalu klik Set target. Kolektor dan periode langsung terisi, tinggal isi target nominal. Target kunjungan, target akun, dan target collection rate bersifat opsional.
- `06:56` Klik Simpan, dan target langsung terlihat di tab Progres maupun Daftar Target.

### Scene 10 — Performa Kolektor  `07:02`

| | |
|---|---|
| **Tampilan / menu** | `/admin/collectors/performance` |
| **Aksi user** | Ganti metrik peringkat ke **Kunjungan** lalu **PTP terpenuhi** |
| **UI yang disorot** | Kartu total tertagih, rata-rata collection rate, total kunjungan, PTP terpenuhi; grafik dan tabel peringkat |
| **Transisi** | Kartu bab 5 |

**Narasi**

- `07:02` Menu Performa Kolektor menampilkan ringkasan total tertagih, rata-rata collection rate, total kunjungan, dan janji bayar yang terpenuhi. Peringkat bisa diurutkan berdasarkan beberapa metrik, bukan hanya nominal.
- `07:18` Misalnya, pilih metrik Kunjungan atau PTP terpenuhi. Grafik dan tabel peringkat langsung menyesuaikan, dan tiga kolektor teratas ditandai.

### Scene 11 — Monitoring Penagihan  `07:29`

| | |
|---|---|
| **Tampilan / menu** | `/collection/monitoring` |
| **Aksi user** | Klik chip status **Menunggak**; urutkan kolom **Tunggakan**; filter Kolektor = **Belum ditugaskan** |
| **UI yang disorot** | Kartu jumlah akun / total tunggakan / akun kritis / akun menunggak; grafik umur tunggakan; distribusi prioritas & status; kolom Kolektor bertanda *Belum ditugaskan* |
| **Transisi** | Ke Lokasi Real-Time |

**Narasi**

- `07:29` Bagian lima: Monitoring Penagihan.
- `07:32` Monitoring Penagihan menampilkan seluruh akun penagihan lintas kolektor: jumlah akun, total tunggakan, akun kritis, akun menunggak, serta grafik umur tunggakan.
- `07:45` Klik chip prioritas atau status untuk memfilter dengan cepat. Filter lain tersedia untuk cluster, blok, unit, customer, alamat, kolektor, umur tunggakan, dan rentang nominal.
- `07:59` Tabel bisa diurutkan menurut prioritas, tunggakan, umur, jatuh tempo, atau kontak terakhir. Pilih Belum ditugaskan di filter kolektor untuk menemukan akun yang belum dipegang siapa pun.

### Scene 12 — Lokasi Real-Time & Surat Penagihan  `08:13`

| | |
|---|---|
| **Tampilan / menu** | `/admin/collectors/live-map` → `/admin/collectors/letters` |
| **Aksi user** | Melihat peta dan daftar surat |
| **UI yang disorot** | Posisi terakhir yang dilaporkan setiap kolektor (dimuat ulang tiap menit); daftar surat (jenis, dibuat oleh, tanggal) dengan tombol **Unduh** |
| **Transisi** | Kartu bab 6 |

**Narasi**

- `08:13` Menu Lokasi Real-Time menampilkan posisi terakhir yang dilaporkan setiap kolektor, dan diperbarui setiap menit. Server hanya menerima lokasi yang dikirim pada jam tugas.
- `08:26` Menu Surat Penagihan dipakai untuk membuat dan mengunduh surat pengingat, peringatan, atau peringatan terakhir dalam format PDF.

### Scene 13 — Tampilan Supervisor  `08:36`

| | |
|---|---|
| **Tampilan / menu** | Login `supervisor.rina` → Dashboard Supervisor → Monitoring Kolektor → Monitoring Penagihan → Notifikasi & Eskalasi |
| **Aksi user** | Menelusuri menu supervisor |
| **UI yang disorot** | Dashboard Supervisor, Monitoring Kolektor, dan Monitoring Penagihan terbatas pada cluster yang diawasi (Alamanda & Bougenville); kartu dashboard; status akun dan waktu laporan lokasi terakhir kolektor; notifikasi PTP jatuh tempo / broken promise dengan aksi Selesai / Eskalasi |
| **Transisi** | Kartu bab 7 |

**Narasi**

- `08:36` Bagian enam: tampilan Supervisor.
- `08:40` Sekarang kita masuk sebagai supervisor. Dashboard Supervisor, Monitoring Kolektor, dan Monitoring Penagihan dibatasi ke cluster yang ditugaskan kepadanya, dalam contoh ini Cluster Alamanda dan Bougenville.
- `08:56` Dashboard Supervisor merangkum kolektor aktif, kolektor yang tidak mengirim lokasi, unit belum bayar, total piutang, pembayaran hari ini, janji bayar aktif, broken promise, dan persetujuan yang menunggu.
- `09:12` Menu Monitoring Kolektor menampilkan status, waktu laporan lokasi terakhir, kunjungan hari ini, dan janji bayar aktif setiap kolektor di wilayahnya.
- `09:23` Supervisor juga bisa membuka Monitoring Penagihan. Isinya otomatis dibatasi hanya untuk cluster yang ia awasi.
- `09:33` Menu Notifikasi dan Eskalasi memberi tahu kondisi yang perlu ditangani, misalnya janji bayar jatuh tempo atau broken promise. Notifikasi bisa ditandai selesai, atau dieskalasi ke pengguna lain yang ditunjuk.

### Scene 14 — Tampilan Kolektor (Web)  `09:48`

| | |
|---|---|
| **Tampilan / menu** | Login `kolektor.budi` → Target & Performa Saya → Rute Kunjungan → Pengingat WhatsApp → Surat Penagihan |
| **Aksi user** | Pengingat: pilih unit AL001, periksa nomor dan pesan, klik **Buka WhatsApp & Catat** |
| **UI yang disorot** | Pencapaian target pribadi; peta wilayah tugas dan unit tanpa koordinat; riwayat pengingat bertambah; surat hanya untuk unit milik kolektor |
| **Transisi** | Kartu penutup |

**Narasi**

- `09:48` Bagian tujuh: tampilan Kolektor di web.
- `09:52` Terakhir, kita masuk sebagai kolektor. Menu khusus kolektor berisi Target dan Performa, Rute Kunjungan, Pengingat WhatsApp, dan Surat Penagihan.
- `10:03` Halaman Target dan Performa Saya menunjukkan nominal terkumpul, pencapaian target, dan jumlah kunjungan untuk periode harian, mingguan, atau bulanan.
- `10:15` Rute Kunjungan menampilkan peta unit yang menjadi wilayah tugas kolektor. Unit tanpa titik koordinat bisa dibuka langsung di Google Maps.
- `10:25` Pengingat WhatsApp: pilih unit, periksa nomor, tulis pesan, lalu klik Buka WhatsApp dan Catat. WhatsApp terbuka di tab baru dengan pesan yang sudah disiapkan, dan pengirimannya langsung tercatat di riwayat di bawahnya.
- `10:41` Surat Penagihan untuk kolektor hanya menampilkan surat milik unit yang ia pegang. Kolektor bisa membuat surat baru dan mengunduhnya sebagai PDF.

### Scene 15 — Penutup  `10:52`

| | |
|---|---|
| **Tampilan / menu** | Kartu *Selesai* |
| **Aksi user** | Tidak ada interaksi |
| **UI yang disorot** | Rujukan checklist uji `docs/testing/15_collector_ui_checklist.md` |
| **Transisi** | Selesai |

**Narasi**

- `10:52` Itulah seluruh alur modul Collector di aplikasi web. Fitur lapangan seperti kunjungan dengan foto dan GPS, janji bayar, dan pengumpulan pembayaran juga tersedia di aplikasi Android untuk kolektor. Terima kasih telah menyimak.

