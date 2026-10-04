# Tutorial Pembayaran Beberapa Bulan Sekaligus & Perhitungan Denda

Video: `tutorial-pembayaran-multi-bulan-denda.mp4` (1920×1080, H.264 + AAC, narasi bahasa Indonesia, subtitle tertanam).
Direkam dari aplikasi yang berjalan (data demo seeder, database SQLite lokal) dengan akun **Loket** (`loket`), pada tanggal 3 Oktober 2026. Contoh: unit **AL002** (Bima Menunggak Saputra, Cluster Alamanda Blok B No 2), 5 tagihan Juni–Oktober 2026.

## 1. Cara aplikasi menghitung denda (dari kode `PenaltyService`)

- **Umur tunggakan** = (tahun sekarang − tahun tagihan) × 12 + (bulan sekarang − bulan tagihan), minimum 0. Berbasis bulan, bukan hari.
- **Denda per tagihan, berjenjang (tier)** sesuai umur tunggakan, bukan dikalikan jumlah bulan. Aturan yang berlaku di data ini:

| Umur tunggakan | Denda per tagihan |
|---|---|
| 0 bulan | Rp 0 |
| 1–2 bulan | Rp 15.000 |
| ≥ 3 bulan | Rp 30.000 |

- Aturan denda dapat dibuat per cluster dan diatur admin (izin `penalty-config.*`); **belum ada halaman frontend** untuk aturan ini, sehingga di video tier dijelaskan lewat narasi dan panel, sementara angkanya diambil dari kolom Umur Tunggakan/Denda di aplikasi.
- Denda dihitung ulang setiap kali tagihan ditampilkan atau dibayar; setelah lunas, denda **dibekukan** (snapshot).
- Urutan alokasi pembayaran: tagihan terlama dulu (FIFO), denda lebih dulu daripada pokok (`penalty_first`). Di modal Bayar, nominal minimal = total tagihan terpilih, jadi pembayaran sebagian tidak dibahas.

**Contoh AL002 (terverifikasi di aplikasi):**

| Periode | Nominal | Umur | Denda | Total |
|---|---|---|---|---|
| Juni 2026 | Rp 350.000 | 4 bulan | Rp 30.000 | Rp 380.000 |
| Juli 2026 | Rp 350.000 | 3 bulan | Rp 30.000 | Rp 380.000 |
| Agustus 2026 | Rp 350.000 | 2 bulan | Rp 15.000 | Rp 365.000 |
| September 2026 | Rp 350.000 | 1 bulan | Rp 15.000 | Rp 365.000 |
| Oktober 2026 | Rp 350.000 | 0 bulan | Rp 0 | Rp 350.000 |
| **Jumlah** | **Rp 1.750.000** | | **Rp 90.000** | **Rp 1.840.000** |

## 2. Validasi workflow

Dijalankan end-to-end saat perekaman: **Tagihan → filter Unit AL002 → ringkasan (5 tagihan, Rp 1.840.000) → centang 5 tagihan → Bayar Terpilih (5) → modal Bayar (Via Loket, nominal otomatis Rp 1.840.000, Cash) → Ringkasan Alokasi (sisa Rp 0) → Proses Bayar → kuitansi GD.2026.10.000001 untuk 5 periode → Riwayat Tagihan: 5 tagihan Lunas, denda tersimpan, tanggal bayar & no. kuitansi sama.**

## 3. Storyboard

### Scene 0 — Pembukaan  `00:00`

| | |
|---|---|
| **Tampilan / menu** | Kartu judul |
| **Aksi user** | Tidak ada interaksi |
| **UI yang disorot** | Judul & tujuan video |
| **Transisi** | Fade ke Dashboard |

**Narasi**

- `00:00` Selamat datang. Video ini menjelaskan cara menerima pembayaran IPL untuk beberapa bulan sekaligus dalam satu transaksi, beserta cara sistem menghitung denda keterlambatan.
- `00:14` Sebagai contoh, kita akan melunasi lima bulan tunggakan sebuah unit, mulai Juni hingga Oktober 2026, lalu memeriksa hasilnya.

### Scene 1 — Membuka Tagihan Unit  `00:26`

| | |
|---|---|
| **Tampilan / menu** | Dashboard → menu Tagihan → filter Unit |
| **Aksi user** | Klik menu Tagihan; pilih unit AL002 pada filter Unit |
| **UI yang disorot** | Menu Tagihan, filter Unit, keterangan "untuk unit AL002" |
| **Transisi** | Daftar tagihan unit tampil |

**Narasi**

- `00:29` Pembayaran beberapa bulan paling mudah dilakukan dari menu Tagihan. Klik menu Tagihan di sidebar.
- `00:37` Halaman ini menampilkan seluruh tagihan yang belum lunas. Untuk memilih satu unit, klik filter Unit, lalu ketik ID unit atau nama penghuni. Pada contoh ini kita ketik AL002.
- `00:52` Pilih unit tersebut. Daftar kini hanya berisi tagihan unit AL002 milik Bima Menunggak Saputra, seperti yang tertulis pada keterangan di bawah judul halaman.

### Scene 2 — Membaca Ringkasan Tunggakan  `01:05`

| | |
|---|---|
| **Tampilan / menu** | Kartu ringkasan di halaman Tagihan |
| **Aksi user** | Tidak ada klik; membaca ringkasan |
| **UI yang disorot** | Total Tagihan Belum Lunas (5 tagihan), Pokok Belum Dibayar, Denda Belum Dibayar |
| **Transisi** | Gulir ke tabel |

**Narasi**

- `01:08` Tiga kartu ringkasan menunjukkan posisi tunggakan unit ini. Total tagihan belum lunas sebanyak lima tagihan, sebesar satu juta delapan ratus empat puluh ribu rupiah.
- `01:21` Angka tersebut terdiri dari pokok IPL yang belum dibayar, satu juta tujuh ratus lima puluh ribu rupiah, ditambah denda yang belum dibayar, sembilan puluh ribu rupiah.

### Scene 3 — Cara Sistem Menghitung Denda  `01:34`

| | |
|---|---|
| **Tampilan / menu** | Tabel tagihan (kolom Periode, Nominal, Umur Tunggakan, Denda, Total) + panel perhitungan |
| **Aksi user** | Geser tabel ke kolom Umur Tunggakan & Denda; tampilkan panel perhitungan |
| **UI yang disorot** | Kolom Umur Tunggakan, Denda, Total; panel tier denda & rincian 5 tagihan |
| **Transisi** | Kembali ke tabel untuk memilih tagihan |

**Narasi**

- `01:37` Setiap baris adalah satu tagihan bulanan. Geser tabel untuk melihat kolom Umur Tunggakan, Denda, dan Total.
- `01:46` Umur tunggakan dihitung dalam bulan, yaitu selisih antara bulan saat ini dan bulan tagihan. Tagihan Oktober 2026 berumur nol bulan, September satu bulan, dan seterusnya hingga Juni yang berumur empat bulan.
- `02:05` Denda dihitung per tagihan secara berjenjang, bukan dikalikan jumlah bulan. Pada aturan denda yang berlaku saat ini, umur nol bulan tidak didenda, umur satu sampai dua bulan didenda lima belas ribu rupiah, dan umur tiga bulan atau lebih didenda tiga puluh ribu rupiah per tagihan.
- `02:26` Jadi untuk lima tagihan ini: Juni dan Juli masing-masing tiga puluh ribu, Agustus dan September masing-masing lima belas ribu, dan Oktober nol. Total denda sembilan puluh ribu rupiah, sehingga total yang harus dibayar satu juta delapan ratus empat puluh ribu rupiah.
- `02:46` Denda dihitung otomatis oleh sistem pada saat pembayaran, sehingga petugas tidak perlu menghitung manual. Aturan nominal denda diatur oleh admin, dan setelah tagihan lunas, dendanya tidak berubah lagi.

### Scene 4 — Memilih Beberapa Bulan  `03:03`

| | |
|---|---|
| **Tampilan / menu** | Tabel tagihan dengan checkbox |
| **Aksi user** | Centang 5 tagihan mulai dari Juni 2026 (terlama) hingga Oktober 2026 |
| **UI yang disorot** | Checkbox tiap baris, tombol "Bayar Terpilih (5)" |
| **Transisi** | Klik Bayar Terpilih |

**Narasi**

- `03:06` Untuk membayar beberapa bulan sekaligus, centang kotak di sebelah kiri setiap tagihan yang akan dibayar. Mulailah dari bulan yang paling lama, yaitu Juni 2026.
- `03:20` Lanjutkan dengan Agustus, September, dan Oktober. Sekarang lima tagihan sudah terpilih. Kotak centang di judul kolom juga dapat dipakai untuk memilih semua tagihan sekaligus.
- `03:34` Perhatikan tombol Bayar Terpilih kini menampilkan angka lima, sesuai jumlah tagihan yang dipilih. Tagihan yang dapat dibayar hanya tagihan yang sudah disetujui.

### Scene 5 — Memeriksa Rincian Pembayaran  `03:47`

| | |
|---|---|
| **Tampilan / menu** | Modal "Bayar Tagihan - Unit AL002" |
| **Aksi user** | Klik Bayar Terpilih; baca rincian 5 periode |
| **UI yang disorot** | Total Dipilih (5 tagihan), tabel Periode/Nominal/Denda/Sisa |
| **Transisi** | Kursor ke form pembayaran |

**Narasi**

- `03:50` Klik Bayar Terpilih. Jendela Bayar Tagihan terbuka dan menampilkan kelima periode yang dipilih, diurutkan dari bulan terlama.
- `04:00` Setiap baris berisi nominal IPL, denda, dan sisa tagihan. Di bagian atas, Total Dipilih untuk lima tagihan adalah satu juta delapan ratus empat puluh ribu rupiah, sama dengan perhitungan tadi.

### Scene 6 — Mengisi Data Pembayaran  `04:16`

| | |
|---|---|
| **Tampilan / menu** | Form di modal Bayar Tagihan + Ringkasan Alokasi |
| **Aksi user** | Periksa Via Loket, Nominal (terisi otomatis = total), Metode Cash; baca Ringkasan Alokasi |
| **UI yang disorot** | Via, Nominal Pembayaran, Metode/Kode Loket/Kasir, Ringkasan Alokasi |
| **Transisi** | Klik Proses Bayar |

**Narasi**

- `04:19` Pastikan Via berisi Loket. Kolom Nominal Pembayaran otomatis terisi sebesar total tagihan yang dipilih, dan nominal tidak boleh kurang dari angka tersebut.
- `04:32` Pilih metode Cash atau Debit dan Transfer sesuai cara penghuni membayar. Kode loket dan nama kasir terisi otomatis.
- `04:41` Gulir ke bawah untuk memeriksa Ringkasan Alokasi. Total tunggakan dan nominal pembayaran sama, yaitu satu juta delapan ratus empat puluh ribu rupiah, seluruhnya teralokasi, dan sisa tunggakan nol.

### Scene 7 — Memproses & Mencetak Kuitansi  `04:57`

| | |
|---|---|
| **Tampilan / menu** | Modal "Pembayaran Berhasil" → menu Cetak Kuitansi |
| **Aksi user** | Klik Proses Bayar; baca hasil; buka menu cetak; Tutup |
| **UI yang disorot** | Nomor kuitansi, daftar 5 periode, Grand Total, opsi cetak |
| **Transisi** | Daftar tagihan belum lunas kosong |

**Narasi**

- `05:00` Klik Proses Bayar. Kelima tagihan dibayar dalam satu transaksi.
- `05:06` Jendela Pembayaran Berhasil menampilkan satu nomor kuitansi untuk seluruh periode, yaitu Juni hingga Oktober 2026, berjumlah lima tagihan, dengan grand total satu juta delapan ratus empat puluh ribu rupiah.
- `05:24` Klik Cetak Kuitansi untuk mencetak bukti pembayaran dalam format A4 atau struk thermal 80 milimeter.
- `05:34` Klik Tutup. Karena seluruh tunggakan sudah dibayar, daftar tagihan belum lunas untuk unit ini kini kosong.

### Scene 8 — Memeriksa Riwayat Tagihan  `05:43`

| | |
|---|---|
| **Tampilan / menu** | Unit Properti → Aksi → Riwayat Tagihan (AL002) |
| **Aksi user** | Cari AL002; buka Riwayat Tagihan; geser ke kolom status & kuitansi |
| **UI yang disorot** | Baris Juni–Oktober 2026: Denda, Dibayar, Status Lunas, Tgl Bayar, No. Kuitansi |
| **Transisi** | Kartu ringkasan & penutup |

**Narasi**

- `05:46` Untuk memastikan pembayaran tercatat, buka menu Unit Properti, cari unit AL002, lalu pilih Riwayat Tagihan pada menu Aksi.
- `05:57` Riwayat Tagihan menampilkan kelima tagihan Juni sampai Oktober 2026. Kolom Denda tetap menyimpan denda yang sudah dibayar, dan kolom Dibayar sama dengan totalnya.
- `06:11` Geser ke kanan. Kelima tagihan kini berstatus Lunas, dan semuanya tercatat dengan tanggal bayar dan nomor kuitansi yang sama.
- `06:24` Sebagai ringkasan: buka menu Tagihan dan pilih unit, pahami denda dari umur tunggakan, centang tagihan mulai dari yang terlama, klik Bayar Terpilih, periksa rincian dan Ringkasan Alokasi, proses pembayaran, cetak kuitansi, lalu pastikan semuanya lunas di Riwayat Tagihan.
- `06:46` Terima kasih telah menyimak. Selamat bertugas.

