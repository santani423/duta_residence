# Tutorial Video — Fitur Unit Properti

Video: `tutorial-unit-properti.mp4` (1920×1080, H.264 + AAC, narasi bahasa Indonesia, subtitle tertanam).
Direkam dari aplikasi yang berjalan (frontend React + API Laravel) dengan data demo seeder pada database SQLite lokal, menggunakan akun **Loket** (`loket`), kecuali bagian Konversi Properti yang memakai akun **Admin Estate**.

## 1. Hasil analisis fitur Unit

| Area | Yang tersedia di aplikasi |
|---|---|
| Akses | Sidebar → **Unit Properti** (`/units`, izin `units.view`). |
| Halaman daftar | Judul, tombol **Refresh**, **Tambah Unit** (izin `units.create`); tabel: ID, Nomor VA, Pemilik, Cluster, Blok, No Unit, Tipe, Status Unit, Status Penghuni, Aksi; paginasi + jumlah per halaman. |
| Pencarian | Kolom "Cari ID, blok, nama pemilik" — mencocokkan ID, blok, nomor unit, nomor VA, nama cluster, nama/no HP pemilik. |
| Filter | Cluster → Blok (bertingkat, blok mengikuti cluster) → Unit; Customer (nama penghuni); Alamat (mis. A/12); Status Unit; Status Penghuni; Tipe. Dihapus per filter lewat ikon ×. |
| Menu Aksi (hover ⋮) | Detail · Detail Penghuni / Masukan Penghuni · Tagihan · Riwayat Tagihan · Pembayaran · Edit · Konversi Properti · Hapus — item disaring sesuai izin. |
| Detail Unit (drawer) | Tab **Informasi** (ID, VA, pemilik, cluster, unit, tipe, status unit/penghuni, kontak, luas, tanggal aktif, catatan; tautan *Masukan Penghuni* bila kosong), **Tagihan** (periode, nominal, umur tunggakan, denda, sisa, status + **Export PDF**), **Akun Portal** (akun login penghuni). |
| Tambah Unit | Cluster*, Blok*, Nomor Unit* (angka saja), Tipe Properti* (Bangunan/Kavling/Ruko), Luas Bangunan, Luas Tanah, Occupancy, Catatan. ID dibuat otomatis (kode cluster + nomor urut, mis. GA031). Duplikat blok+nomor di cluster yang sama ditolak. |
| Edit Unit | Field Tambah Unit + **Pemilik / Penghuni** dan **Nomor Virtual Account** (prefix bank+perusahaan otomatis + 9 digit). Perubahan dicatat di audit log. |
| Status Unit (otomatis) | Ready Stock (bangunan/ruko tanpa penghuni), Tanah Kosong (kavling tanpa penghuni), Booked (sudah ditautkan, belum aktif), Occupied (penghuni aktif). Status Penghuni: Aktif / Properti Kosong / Tidak Aktif. |
| Masukan Penghuni | Mode **Penghuni Baru** (Nama*, VA*, No HP*, Email*, Username opsional, identitas, kab/kota, alamat KTP, kontak darurat, catatan) atau **Penghuni yang Sudah Ada** (pilih penghuni + VA). Unit langsung aktif (Occupied/Aktif). Penghuni baru → akun login portal dibuat otomatis, password sementara tampil sekali. |
| Detail Penghuni | Halaman profil: Ringkasan keuangan, Data Penghuni, Data Unit (Edit kepemilikan/penyewa), Transaksi, Saldo, Komplain, Kunjungan Collector. |
| Tagihan (dari Aksi) | Halaman Tagihan terfilter unit: ringkasan (total belum lunas, pokok, denda), tabel tagihan, **Bayar** per baris / **Bayar Terpilih**, Download PDF/Excel. Bayar hanya untuk tagihan yang sudah di-approve. |
| Bayar Tagihan (modal) | Saldo unit, tagihan dipilih, Via (Loket/…), nominal, gunakan saldo, Metode (Cash, Debit/Transfer), Kode Loket, Nama Kasir (otomatis), Catatan, **Ringkasan Alokasi**; kelebihan bayar → saldo unit. Hasil: nomor kuitansi + Cetak Kuitansi. |
| Riwayat Tagihan | Semua tagihan semua tahun & status, termasuk Tgl Bayar dan No. Kuitansi; Download PDF/Excel. |
| Pembayaran (dari Aksi) | Banner "Menampilkan transaksi dan kuitansi … unit X" + *Tampilkan semua unit*; tab Proses Pembayaran (cari tagihan unit, Saldo Unit, Total Tunggakan, Tagihan Mendatang, cetak tagihan/riwayat), Transaksi Gateway (via, metode, **Bukti** transfer, status, Detail/Cetak/Verifikasi/Tolak), Riwayat Kuitansi (cetak ulang, export). |
| Konversi Properti | Hanya unit tipe Kavling, izin `units.convert-property` (Admin Estate/Super Admin; **tidak** untuk Loket). Mengubah tipe ke Bangunan + tanggal serah terima hari ini. |
| Hapus | Konfirmasi "Hapus unit?" + ID; soft delete. Izin `units.delete` (Loket & Super Admin; tidak untuk Admin Estate). |

**Catatan temuan saat eksplorasi**
- Tab **Saldo** di Detail Penghuni tampil untuk Loket, tetapi Loket tidak memiliki izin `balances.view`, sehingga ledger dan rekonsiliasi saldo kosong (API 403). Di video, informasi saldo ditunjukkan lewat modal Bayar dan tab Proses Pembayaran.
- Pada halaman Detail Penghuni, menu sidebar yang tersorot adalah *Dashboard*, bukan menu asal.
- Fitur *Konversi Properti* tidak tersedia untuk Loket; ditampilkan memakai akun Admin Estate.

## 2. Validasi workflow

Workflow yang divalidasi end-to-end saat perekaman (semua aksi benar-benar dijalankan di aplikasi):

**Login → Unit Properti → Cari/Filter → Detail → Tambah Unit (GA031) → Edit → Masukan Penghuni (akun portal dibuat, status jadi Occupied) → Detail Penghuni → Tagihan GA005 → Bayar (lebih bayar → saldo) → Riwayat Tagihan (status Lunas + no. kuitansi) → Pembayaran (kuitansi, transaksi, saldo unit) → Hapus (dibatalkan) → Konversi Properti (Admin Estate, dibatalkan)**

## 3. Storyboard

### Scene 1 — Pembukaan & Tujuan Fitur Unit  `00:00`

| | |
|---|---|
| **Tampilan / menu** | Kartu judul video |
| **Aksi user** | Tidak ada interaksi; pengantar materi |
| **UI yang disorot** | Judul tutorial dan sasaran pengguna (Admin & Loket) |
| **Transisi** | Fade ke halaman Login |

**Narasi**

- `00:00` Selamat datang di tutorial fitur Unit Properti pada aplikasi manajemen perumahan Duta Indah Residences.
- `00:09` Unit adalah data induk untuk setiap rumah, kavling, atau ruko. Tagihan, pembayaran, saldo, dan data penghuni semuanya terhubung ke sebuah unit. Karena itu, data unit yang rapi menjadi dasar seluruh pekerjaan admin dan loket.
- `00:28` Dalam video ini, kita akan mencari unit, menambah dan mengubah data unit, menghubungkan unit dengan penghuni, lalu memproses pembayaran dan memeriksa riwayatnya.

### Scene 2 — Mengakses Menu Unit  `00:40`

| | |
|---|---|
| **Tampilan / menu** | Halaman Login → Dashboard → sidebar |
| **Aksi user** | Isi username & password, klik Login, klik menu Unit Properti |
| **UI yang disorot** | Form login, tombol Login, menu sidebar "Unit Properti" |
| **Transisi** | Halaman Unit Properti terbuka |

**Narasi**

- `00:43` Pertama, masuk ke aplikasi menggunakan username dan password akun Anda. Pada contoh ini, kita menggunakan akun Loket.
- `00:53` Klik tombol Login. Setelah berhasil, Anda akan diarahkan ke halaman Dashboard.
- `01:00` Menu di sebelah kiri menyesuaikan hak akses akun Anda. Untuk mengelola unit, klik menu Unit Properti.

### Scene 3 — Mengenal Halaman Daftar Unit  `01:08`

| | |
|---|---|
| **Tampilan / menu** | Unit Properti (daftar unit) |
| **Aksi user** | Menunjuk header, area filter, kolom tabel; geser tabel ke kanan; gulir ke paginasi |
| **UI yang disorot** | Tombol Refresh & Tambah Unit, area filter, header tabel, kolom Status Penghuni & Aksi, paginasi |
| **Transisi** | Kursor menuju kolom pencarian |

**Narasi**

- `01:11` Inilah halaman Unit Properti, yaitu daftar seluruh unit beserta pemiliknya. Di kanan atas terdapat tombol Refresh untuk memuat ulang data, dan tombol Tambah Unit untuk mendaftarkan unit baru.
- `01:26` Di bawahnya terdapat area pencarian dan filter untuk mempersempit daftar unit.
- `01:33` Tabel menampilkan ID unit, Nomor Virtual Account untuk pembayaran, nama pemilik, cluster, blok, nomor unit, tipe properti, dan Status Unit.
- `01:45` Geser tabel ke kanan untuk melihat kolom Status Penghuni. Kolom Aksi di sisi kanan berisi seluruh tindakan untuk setiap unit, yang akan kita bahas satu per satu.
- `01:58` Di bagian bawah tabel terdapat navigasi halaman dan pilihan jumlah data per halaman.

### Scene 4 — Mencari & Memfilter Unit  `02:07`

| | |
|---|---|
| **Tampilan / menu** | Unit Properti — area filter |
| **Aksi user** | Ketik kata kunci; pilih Cluster → Blok → Status Unit; hapus filter |
| **UI yang disorot** | Kolom pencarian, dropdown Cluster/Blok/Status Unit, filter lain |
| **Transisi** | Daftar kembali tanpa filter |

**Narasi**

- `02:10` Untuk mencari unit, ketik kata kunci pada kolom pencarian. Anda dapat mencari berdasarkan ID unit, blok, nomor unit, nomor VA, nama cluster, maupun nama atau nomor HP pemilik. Daftar akan langsung menyesuaikan.
- `02:28` Selain pencarian, gunakan filter. Pilih Cluster untuk menampilkan unit di cluster tertentu.
- `02:35` Setelah cluster dipilih, pilihan Blok hanya menampilkan blok yang ada di cluster tersebut, sehingga unit lebih cepat ditemukan.
- `02:46` Filter lain dapat digabungkan, seperti Status Unit, Status Penghuni, dan Tipe properti. Tersedia juga pencarian berdasarkan nama customer dan alamat, misalnya blok A nomor 12.
- `03:01` Untuk menghapus filter, klik ikon silang pada masing-masing filter. Daftar akan kembali menampilkan semua unit.

### Scene 5 — Membuka Detail Unit  `03:10`

| | |
|---|---|
| **Tampilan / menu** | Drawer "Detail Unit" (tab Informasi, Tagihan, Akun Portal) |
| **Aksi user** | Arahkan ke tombol Aksi → Detail; pindah tab; tutup drawer |
| **UI yang disorot** | Menu Aksi, tabel Informasi, tombol Export PDF, tab Akun Portal |
| **Transisi** | Drawer ditutup, kembali ke daftar |

**Narasi**

- `03:13` Untuk melihat informasi lengkap sebuah unit, arahkan kursor ke tombol Aksi pada baris unit, lalu pilih Detail.
- `03:22` Panel Detail Unit terbuka. Tab Informasi menampilkan nomor VA, pemilik, cluster, blok dan nomor, tipe, Status Unit, Status Penghuni, kontak pemilik, luas bangunan dan tanah, tanggal aktif, serta catatan.
- `03:40` Tab Tagihan menampilkan daftar tagihan unit ini, lengkap dengan nominal, umur tunggakan, denda, sisa tagihan, dan statusnya. Tombol Export PDF digunakan untuk mengunduh rekap tagihan unit.
- `03:56` Tab Akun Portal menampilkan akun login penghuni yang terhubung ke unit ini, termasuk status akun dan waktu login terakhir.
- `04:06` Tutup panel dengan tombol silang untuk kembali ke daftar unit.

### Scene 6 — Mengelola Data Unit: Menu Aksi  `04:11`

| | |
|---|---|
| **Tampilan / menu** | Unit Properti — dropdown Aksi |
| **Aksi user** | Arahkan kursor ke tombol Aksi dan tahan |
| **UI yang disorot** | Seluruh item menu Aksi |
| **Transisi** | Kursor menuju tombol Tambah Unit |

**Narasi**

- `04:14` Mari kenali menu Aksi. Detail untuk melihat informasi unit. Detail Penghuni untuk membuka profil penghuni. Tagihan, Riwayat Tagihan, dan Pembayaran untuk membuka data keuangan unit ini. Edit untuk mengubah data unit, dan Hapus untuk menghapusnya.
- `04:34` Jika unit belum memiliki penghuni, menu Detail Penghuni berganti menjadi Masukan Penghuni. Daftar menu juga menyesuaikan hak akses akun yang sedang login.

### Scene 7 — Menambahkan Unit  `04:46`

| | |
|---|---|
| **Tampilan / menu** | Drawer "Tambah Unit" |
| **Aksi user** | Isi Cluster, Blok, Nomor Unit, Tipe, Luas, Catatan → Simpan; cari unit baru |
| **UI yang disorot** | Field wajib, tooltip Nomor Unit, tombol Simpan, baris unit baru |
| **Transisi** | Unit baru GA031 tampil di daftar |

**Narasi**

- `04:49` Sekarang kita tambahkan unit baru. Klik tombol Tambah Unit. Form Tambah Unit akan terbuka di sisi kanan.
- `04:59` Pilih Cluster tempat unit berada, lalu isi Blok.
- `05:05` Isi Nomor Unit dengan angka saja, misalnya delapan puluh delapan, tanpa angka nol di depan. Sistem akan menolak jika kombinasi blok dan nomor yang sama sudah terdaftar di cluster tersebut.
- `05:20` Pilih Tipe Properti: Bangunan, Kavling, atau Ruko. Kemudian isi luas bangunan dan luas tanah dalam meter persegi.
- `05:30` Kolom Occupancy dan Catatan bersifat opsional. Perhatikan, penghuni dan nomor virtual account belum diisi di sini, karena keduanya ditambahkan nanti melalui menu Masukan Penghuni.
- `05:44` Klik Simpan. Muncul notifikasi Unit berhasil disimpan, dan ID unit dibuat otomatis dari kode cluster ditambah nomor urut.
- `05:55` Cari unit baru tersebut. Unit GA031 sudah terdaftar dengan status Ready Stock, artinya bangunan siap tetapi belum memiliki penghuni. Nomor VA dan pemilik masih kosong.

### Scene 8 — Mengedit Unit  `06:09`

| | |
|---|---|
| **Tampilan / menu** | Drawer "Edit Unit" |
| **Aksi user** | Aksi → Edit; ubah Luas Bangunan; Simpan |
| **UI yang disorot** | Field Pemilik/Penghuni & Nomor Virtual Account (khusus Edit), Luas Bangunan |
| **Transisi** | Notifikasi tersimpan, kembali ke daftar |

**Narasi**

- `06:12` Untuk mengubah data unit, pilih Edit pada menu Aksi.
- `06:17` Pada form Edit terdapat dua kolom tambahan, yaitu Pemilik atau Penghuni, dan Nomor Virtual Account. Nomor VA terdiri dari kode bank dan kode perusahaan yang terisi otomatis, ditambah sembilan digit nomor unik.
- `06:33` Pada contoh ini, kita perbaiki luas bangunan menjadi tujuh puluh lima meter persegi.
- `06:40` Klik Simpan. Perubahan langsung tersimpan, dan setiap perubahan data unit tercatat di log audit sistem.

### Scene 9 — Memahami Status Unit  `06:49`

| | |
|---|---|
| **Tampilan / menu** | Panel penjelasan status di atas halaman Unit |
| **Aksi user** | Tidak ada interaksi; penjelasan status otomatis |
| **UI yang disorot** | Empat Status Unit dan tiga Status Penghuni |
| **Transisi** | Panel ditutup, kembali ke unit GA031 |

**Narasi**

- `06:52` Status Unit tidak diisi manual, melainkan dihitung otomatis oleh sistem. Ready Stock berarti bangunan atau ruko belum berpenghuni. Tanah Kosong berarti kavling belum berpenghuni. Booked berarti unit sudah ditautkan ke penghuni namun belum aktif. Occupied berarti unit sudah memiliki penghuni aktif.
- `07:14` Sementara itu, Status Penghuni menunjukkan kondisi hunian unit, yaitu Aktif, Properti Kosong, atau Tidak Aktif. Unit langsung menjadi Aktif dan Occupied ketika penghuni dimasukkan bersama nomor virtual account-nya, seperti yang akan kita lakukan berikut ini.

### Scene 10 — Menghubungkan Unit dengan Penghuni  `07:34`

| | |
|---|---|
| **Tampilan / menu** | Drawer "Detail Unit" → Drawer "Masukan Penghuni" → modal akun login |
| **Aksi user** | Klik Masukan Penghuni; tinjau dua mode; isi data penghuni baru; Simpan; catat akun login |
| **UI yang disorot** | Tautan Masukan Penghuni, pilihan Penghuni Baru / Sudah Ada, field wajib, modal akun login, baris unit setelah update |
| **Transisi** | Unit GA031 berstatus Occupied |

**Narasi**

- `07:37` Sekarang kita hubungkan unit dengan penghuni. Menu Masukan Penghuni bisa dibuka dari menu Aksi, atau dari tautan pada kolom Pemilik di panel Detail Unit.
- `07:50` Terdapat dua pilihan. Penghuni Baru, untuk mendaftarkan orang yang belum ada di sistem. Dan Penghuni yang Sudah Ada, untuk menautkan unit ke penghuni yang datanya sudah terdaftar.
- `08:04` Pada mode Penghuni yang Sudah Ada, cukup pilih nama penghuni dan isi nomor virtual account, lalu simpan.
- `08:12` Pada contoh ini, kita pilih Penghuni Baru. Kolom Unit sudah terisi otomatis sesuai unit yang dipilih.
- `08:21` Isi nama penghuni dan sembilan digit nomor virtual account. Nomor VA harus unik, karena menjadi nomor pembayaran untuk unit ini.
- `08:32` Lengkapi Nomor HP dan Email yang wajib diisi. Username akun login boleh dikosongkan agar dibuat otomatis. Data identitas, alamat, dan kontak darurat bersifat opsional.
- `08:46` Klik Simpan. Sistem membuat data penghuni, menautkannya ke unit, dan otomatis membuat akun login portal untuk penghuni tersebut.
- `08:57` Catat username dan password sementara ini, lalu sampaikan kepada penghuni, karena password hanya ditampilkan satu kali.
- `09:07` Klik OK. Kini unit GA031 sudah memiliki pemilik dan nomor VA lengkap. Status Unit berubah menjadi Occupied, dan Status Penghuni menjadi Aktif.

### Scene 11 — Mengelola Data Penghuni dari Unit  `09:21`

| | |
|---|---|
| **Tampilan / menu** | Halaman profil penghuni (dibuka dari Aksi → Detail Penghuni) |
| **Aksi user** | Aksi → Detail Penghuni; buka tab Data Penghuni dan Data Unit |
| **UI yang disorot** | Menu Detail Penghuni, deretan tab, kartu Akun Login, tombol Edit di kartu unit |
| **Transisi** | Kembali ke Unit Properti lewat sidebar |

**Narasi**

- `09:24` Karena unit sudah berpenghuni, menu Masukan Penghuni berganti menjadi Detail Penghuni. Pilih menu ini untuk membuka profil penghuni.
- `09:34` Halaman penghuni menampilkan ringkasan keuangan, serta tab Data Penghuni, Data Unit, Transaksi, Saldo, Komplain, dan Kunjungan Collector.
- `09:46` Tab Data Penghuni berisi kontak, identitas, kontak darurat, dan informasi akun login portal.
- `09:55` Tab Data Unit menampilkan unit milik penghuni beserta status kepemilikan dan penanggung jawab tagihan. Tombol Edit di sini digunakan untuk mengubah data hunian, misalnya pemilik atau penyewa.
- `10:10` Jika pemilik unit perlu diganti, gunakan menu Edit pada unit, lalu ubah kolom Pemilik atau Penghuni. Sekarang kita kembali ke menu Unit Properti.

### Scene 12 — Tagihan & Pembayaran di Loket  `10:21`

| | |
|---|---|
| **Tampilan / menu** | Halaman Tagihan (terfilter unit GA005) → modal "Bayar Tagihan" → modal "Pembayaran Berhasil" |
| **Aksi user** | Cari GA005; Aksi → Tagihan; klik Bayar; isi nominal 400.000; Proses Bayar; Tutup |
| **UI yang disorot** | Kartu ringkasan, kolom tagihan, tombol Bayar, Via/Metode/Kasir, Ringkasan Alokasi, nomor kuitansi & info saldo |
| **Transisi** | Tagihan terbayar hilang dari daftar belum lunas |

**Narasi**

- `10:24` Selanjutnya kita bahas pembayaran. Sebagai contoh, kita cari unit GA005 yang masih memiliki tunggakan.
- `10:34` Pilih menu Tagihan pada Aksi. Halaman Tagihan terbuka dan otomatis terfilter hanya untuk unit GA005.
- `10:43` Bagian ringkasan menampilkan total tagihan belum lunas, pokok yang belum dibayar, dan denda yang belum dibayar.
- `10:53` Setiap baris adalah satu tagihan bulanan, lengkap dengan periode, nominal, diskon, umur tunggakan, denda, sisa tagihan, status, dan status persetujuan. Tagihan harus sudah disetujui sebelum dapat dibayar.
- `11:12` Untuk menerima pembayaran di loket, klik tombol Bayar pada tagihan yang akan dibayar. Anda juga dapat mencentang beberapa tagihan, lalu klik Bayar Terpilih.
- `11:25` Jendela Bayar Tagihan menampilkan saldo unit, total tagihan yang dipilih, dan rincian periodenya.
- `11:33` Pilih jalur pembayaran Loket, dan metode Cash atau Debit dan Transfer. Nama kasir terisi otomatis sesuai akun yang sedang login.
- `11:44` Isi nominal yang diterima. Jika nominal melebihi tunggakan, misalnya empat ratus ribu rupiah untuk tagihan tiga ratus tiga puluh ribu, Ringkasan Alokasi menunjukkan bahwa kelebihan tujuh puluh ribu rupiah akan ditambahkan ke saldo unit.
- `12:03` Klik Proses Bayar. Pembayaran berhasil, nomor kuitansi terbit, dan kelebihan pembayaran dicatat sebagai saldo unit. Saldo ini dapat digunakan untuk membayar tagihan berikutnya.
- `12:17` Gunakan tombol Cetak Kuitansi untuk mencetak bukti pembayaran, lalu klik Tutup. Tagihan yang sudah dibayar otomatis hilang dari daftar tagihan belum lunas.

### Scene 13 — Riwayat Tagihan & Transaksi  `12:30`

| | |
|---|---|
| **Tampilan / menu** | Riwayat Tagihan → Pembayaran (tab Riwayat Kuitansi, Transaksi Gateway, Proses Pembayaran) |
| **Aksi user** | Aksi → Riwayat Tagihan; periksa status Lunas; Aksi → Pembayaran; pindah tab; Cari Tagihan |
| **UI yang disorot** | Status Lunas + Tgl Bayar + No. Kuitansi, tombol unduh, banner filter unit, kuitansi baru, kolom Bukti & Verifikasi, Saldo Unit |
| **Transisi** | Kembali ke Unit Properti |

**Narasi**

- `12:33` Untuk melihat riwayat lengkap, kembali ke Unit Properti, lalu pilih Riwayat Tagihan pada menu Aksi unit GA005.
- `12:43` Riwayat Tagihan menampilkan semua tagihan dari semua tahun, baik yang belum dibayar maupun yang sudah lunas. Tagihan yang baru saja dibayar kini berstatus Lunas, lengkap dengan tanggal bayar dan nomor kuitansinya.
- `13:03` Riwayat ini dapat diunduh dalam format PDF atau Excel melalui tombol di kanan atas.
- `13:11` Berikutnya, pilih menu Pembayaran pada Aksi unit.
- `13:21` Halaman Pembayaran terbuka dengan data khusus unit GA005, ditandai keterangan di bagian atas. Tab Riwayat Kuitansi menampilkan kuitansi yang baru saja terbit beserta kuitansi sebelumnya. Setiap kuitansi dapat dicetak ulang.
- `13:40` Tab Transaksi Gateway menampilkan seluruh transaksi unit, baik dari loket maupun pembayaran online. Untuk transfer manual, kolom Bukti menampilkan bukti transfer yang diunggah, dan petugas dapat memverifikasi atau menolaknya.
- `13:58` Pada tab Proses Pembayaran, pilih unit lalu klik Cari Tagihan. Di sini terlihat Saldo Unit sebesar tujuh puluh ribu rupiah dari kelebihan pembayaran tadi, beserta total tunggakan dan tagihan mendatang.

### Scene 14 — Action Tambahan  `14:14`

| | |
|---|---|
| **Tampilan / menu** | Unit Properti — konfirmasi Hapus; login Admin Estate — modal "Konversi Kavling" |
| **Aksi user** | Aksi → Hapus → Batal; (akun Admin Estate) filter Tipe Kavling → Aksi → Konversi Properti → Batal |
| **UI yang disorot** | Dialog konfirmasi hapus, menu Konversi Properti, form tipe tujuan |
| **Transisi** | Panel relasi data |

**Narasi**

- `14:17` Menu Hapus digunakan untuk menghapus unit yang salah input. Sistem selalu meminta konfirmasi terlebih dahulu, dan unit yang dihapus tidak lagi tampil di daftar. Pastikan ID unit pada konfirmasi sudah benar.
- `14:34` Pada contoh ini kita klik Batal, sehingga data unit tidak berubah.
- `14:43` Fitur Konversi Properti hanya tersedia untuk role tertentu, seperti Admin Estate, dan hanya aktif untuk unit bertipe Kavling. Fitur ini digunakan ketika kavling sudah dibangun dan berubah menjadi bangunan.
- `14:59` Pilih tipe tujuan Bangunan dan isi catatan bila perlu. Setelah disimpan, tipe unit berubah menjadi Bangunan dan tanggal serah terima diisi dengan tanggal hari itu. Kita klik Batal untuk menutup contoh ini.

### Scene 15 — Hubungan Antar-Data Unit  `15:15`

| | |
|---|---|
| **Tampilan / menu** | Panel diagram relasi |
| **Aksi user** | Tidak ada interaksi |
| **UI yang disorot** | Cluster → Unit → Penghuni → Akun Portal; Unit → Tagihan → Pembayaran → Kuitansi & Saldo |
| **Transisi** | Panel ringkasan workflow |

**Narasi**

- `15:18` Mari rangkum hubungan antar-data. Setiap unit berada di satu cluster dan blok. Unit terhubung ke penghuni sebagai pemilik, dan penghuni memiliki akun portal untuk login.
- `15:31` Setiap bulan, unit memiliki tagihan. Pembayaran tagihan menghasilkan kuitansi, dan kelebihan pembayaran disimpan sebagai saldo unit. Semua data ini dapat ditelusuri dari menu Aksi pada halaman Unit Properti.

### Scene 16 — Ringkasan Workflow Unit  `15:49`

| | |
|---|---|
| **Tampilan / menu** | Panel ringkasan langkah + kartu penutup |
| **Aksi user** | Tidak ada interaksi |
| **UI yang disorot** | Urutan langkah kerja dari awal sampai selesai |
| **Transisi** | Selesai |

**Narasi**

- `15:52` Sebagai ringkasan: buka menu Unit Properti, cari dan filter unit, lalu buka detailnya. Tambah atau edit data unit bila diperlukan, kemudian masukkan penghuni beserta nomor virtual account-nya.
- `16:07` Untuk pembayaran, buka Tagihan dari menu Aksi, klik Bayar, dan cetak kuitansinya. Terakhir, periksa Riwayat Tagihan dan halaman Pembayaran untuk memastikan semua transaksi tercatat dengan benar.
- `16:23` Terima kasih telah menyimak. Selamat mengelola unit properti di aplikasi Duta Indah Residences.

