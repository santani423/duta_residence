# Tutorial Pembayaran IPL melalui Loket – Estate Management

Video: `tutorial-pembayaran-ipl-loket.mp4` (1920×1080, H.264 + AAC, narasi bahasa Indonesia, subtitle tertanam).
Direkam dari aplikasi yang berjalan (data demo seeder, database SQLite lokal) dengan akun **Loket** (`loket`). Contoh transaksi: unit **AL013** (Demarcus Powlowski, Cluster Alamanda Blok A No 13), periode **Oktober 2026**, Rp 350.000.

## 1. Kesesuaian brief dengan aplikasi

| Brief | Kondisi di aplikasi | Di video |
|---|---|---|
| Menu **Loket** | Tidak ada menu bernama Loket. Transaksi loket ada di sidebar **Pembayaran** | Menu Pembayaran |
| Pilihan **Transaksi Normal** | Tidak ada pilihan jenis transaksi | Tab **Proses Pembayaran** + Via **Loket** |
| Hanya **1 bulan** per transaksi | Tidak dipaksa sistem: tagihan dipilih dengan checkbox (bisa lebih dari satu). Kolom **Periode Tagihan** adalah pemilih bulan (awal–akhir) yang menyaring tagihan per periode | Dinarasikan sebagai aturan transaksi normal; dipilih Okt 2026 s.d. Okt 2026, sehingga hanya 1 tagihan yang tampil dan dipilih |
| **Diskon** | Tidak ada kolom diskon di tabel Proses Pembayaran | Tidak disebut |
| Metode pembayaran | Via: **Loket**, **Transfer** (gateway lain hanya tampil jika diaktifkan). Metode: **Cash**, **Debit/Transfer**. Channel (opsional): Loket, Bank Transfer, QRIS | Semua pilihan ditampilkan; dipilih Loket + Cash |
| **Modal konfirmasi** | Tidak ada. Tombol **Proses Bayar Loket** langsung memproses | Pemeriksaan dilakukan di panel **Ringkasan Alokasi** sebelum klik |
| Hasil transaksi | Notifikasi + modal **Pembayaran Berhasil**: nomor kuitansi, tanggal/jam, penghuni & alamat unit, periode, jumlah tagihan, total tagihan, denda, grand total, metode, kode loket, kasir. **Cetak Kuitansi**: Kuitansi (A4), Struk (Thermal 80mm). Kuitansi tersimpan di **Riwayat Kuitansi** | Ditampilkan |

## 2. Validasi workflow

Dijalankan end-to-end saat perekaman: **Pembayaran → Proses Pembayaran → pilih unit AL013 → Periode Okt 2026–Okt 2026 → Cari Tagihan (1 tagihan, Rp 350.000) → Via Loket, nominal Rp 350.000, Cash → cek Ringkasan Alokasi (sisa tunggakan Rp 0) → Proses Bayar Loket → kuitansi GD.2026.10.000001 → tampil di Riwayat Kuitansi.** Tidak ada pemilihan lebih dari satu bulan.

## 3. Storyboard

### Scene 0 — Pembukaan  `00:00`

| | |
|---|---|
| **Tampilan / menu** | Kartu judul |
| **Aksi user** | Tidak ada interaksi |
| **UI yang disorot** | Judul video & sasaran penonton |
| **Transisi** | Fade ke Dashboard |

**Narasi**

- `00:00` Selamat datang. Video ini menjelaskan cara petugas loket menerima pembayaran IPL, atau Iuran Pemeliharaan Lingkungan, untuk transaksi normal: satu unit dan satu bulan dalam satu transaksi.
- `00:16` Kita akan mencari unit, memilih periode IPL, memeriksa tagihan, memproses pembayaran, hingga kuitansi terbit.

### Scene 1 — Membuka Menu Pembayaran  `00:26`

| | |
|---|---|
| **Tampilan / menu** | Dashboard → sidebar → halaman Pembayaran |
| **Aksi user** | Klik menu Pembayaran di sidebar |
| **UI yang disorot** | Menu sidebar "Pembayaran" |
| **Transisi** | Halaman Pembayaran terbuka |

**Narasi**

- `00:29` Ini adalah halaman utama aplikasi setelah petugas loket login. Di aplikasi ini, transaksi pembayaran dari penghuni di loket dilakukan melalui menu Pembayaran.
- `00:43` Klik menu Pembayaran untuk membuka halaman transaksi.

### Scene 2 — Membuka Proses Pembayaran  `00:47`

| | |
|---|---|
| **Tampilan / menu** | Halaman Pembayaran — tab Proses Pembayaran |
| **Aksi user** | Menunjuk tab Proses Pembayaran (aktif secara default) |
| **UI yang disorot** | Deretan tab; tab "Proses Pembayaran" |
| **Transisi** | Kursor menuju kolom Unit |

**Narasi**

- `00:50` Halaman Pembayaran memiliki tiga tab: Proses Pembayaran, Transaksi Gateway, dan Riwayat Kuitansi.
- `00:59` Untuk transaksi normal di loket, gunakan tab Proses Pembayaran. Tab ini sudah terbuka secara otomatis. Tutorial ini khusus untuk pembayaran IPL satu bulan melalui loket.

### Scene 3 — Memilih Unit  `01:13`

| | |
|---|---|
| **Tampilan / menu** | Tab Proses Pembayaran — filter & kolom Unit |
| **Aksi user** | Ketik "AL013" di kolom Unit, pilih unit dari daftar |
| **UI yang disorot** | Filter pencarian, kolom Unit, opsi unit (ID, cluster, blok/no, penghuni) |
| **Transisi** | Kursor menuju Periode Tagihan |

**Narasi**

- `01:16` Pertama, tentukan unit yang akan membayar. Jika diperlukan, persempit pencarian dengan filter Cluster, Blok, nama customer, atau alamat.
- `01:28` Klik kolom Unit, lalu ketik ID unit, alamat, atau nama penghuni. Pada contoh ini, kita ketik AL013.
- `01:38` Setiap pilihan menampilkan ID unit, cluster, blok dan nomor, serta nama penghuni. Pastikan ini unit yang benar, lalu pilih unit tersebut.

### Scene 4 — Memilih Periode IPL  `01:49`

| | |
|---|---|
| **Tampilan / menu** | Kolom "Periode Tagihan" (pemilih bulan) |
| **Aksi user** | Buka pemilih, klik Okt 2026 sebagai awal dan akhir, klik Cari Tagihan |
| **UI yang disorot** | Kolom Periode Tagihan, bulan Okt 2026, tombol Cari Tagihan |
| **Transisi** | Kartu tagihan unit tampil |

**Narasi**

- `01:52` Selanjutnya, pilih periode IPL yang akan dibayarkan melalui kolom Periode Tagihan. Pada transaksi normal, petugas hanya memilih satu periode IPL dalam satu transaksi.
- `02:07` Karena hanya satu bulan, pilih bulan yang sama sebagai awal dan akhir periode. Pada contoh ini, klik Oktober 2026 dua kali.
- `02:19` Klik Cari Tagihan. Sistem menampilkan tagihan IPL unit tersebut untuk periode yang dipilih.

### Scene 5 — Memeriksa Detail Tagihan  `02:26`

| | |
|---|---|
| **Tampilan / menu** | Kartu unit AL013: ringkasan & tabel tagihan |
| **Aksi user** | Gulir ke tabel, berhenti untuk dibaca |
| **UI yang disorot** | Judul kartu (unit, penghuni, cluster), Saldo/Total Tunggakan/Tagihan Mendatang, baris Oktober 2026, teks "Dipilih: 1 dari 1 tagihan" |
| **Transisi** | Kursor menuju pilihan Via |

**Narasi**

- `02:29` Bagian atas menampilkan ID unit, nama penghuni, dan cluster. Di bawahnya terdapat Saldo Unit, Total Tunggakan, dan Tagihan Mendatang.
- `02:41` Tabel tagihan menampilkan periode Oktober 2026, tanggal jatuh tempo, nominal IPL, denda, total tagihan, jumlah yang sudah dibayar, sisa tagihan, dan statusnya. Untuk contoh ini, nominalnya tiga ratus lima puluh ribu rupiah tanpa denda, dengan status Belum Bayar.
- `03:04` Pastikan hanya satu tagihan yang tercentang. Keterangan di bawah tabel menunjukkan satu dari satu tagihan dipilih, dengan total tiga ratus lima puluh ribu rupiah.

### Scene 6 — Melakukan Pembayaran  `03:17`

| | |
|---|---|
| **Tampilan / menu** | Form pembayaran loket di kartu unit |
| **Aksi user** | Pilih Via Loket; isi nominal 350.000; buka pilihan Metode (Cash, Debit/Transfer) dan pilih Cash; tunjukkan Channel, Kode Loket, Nama Kasir |
| **UI yang disorot** | Via, Nominal Pembayaran, Metode, Channel, Kode Loket, Nama Kasir |
| **Transisi** | Gulir ke Ringkasan Alokasi |

**Narasi**

- `03:20` Sekarang isi data pembayaran. Pada kolom Via tersedia pilihan Loket dan Transfer. Untuk pembayaran langsung di loket, pilih Loket.
- `03:30` Isi Nominal Pembayaran sesuai uang yang diterima dari penghuni, yaitu tiga ratus lima puluh ribu rupiah.
- `03:40` Pada kolom Metode, tersedia dua pilihan, yaitu Cash dan Debit atau Transfer. Pilih sesuai cara penghuni membayar. Pada contoh ini, kita pilih Cash.
- `03:52` Kolom Channel bersifat opsional, dengan pilihan Loket, Bank Transfer, atau QRIS. Kode Loket terisi L01 secara default, dan Nama Kasir terisi otomatis sesuai akun yang sedang login. Catatan dapat diisi bila perlu.

### Scene 7 — Memeriksa Sebelum Memproses  `04:10`

| | |
|---|---|
| **Tampilan / menu** | Panel "Ringkasan Alokasi" + tombol Proses Bayar Loket |
| **Aksi user** | Periksa unit, periode, nominal, total; klik Proses Bayar Loket |
| **UI yang disorot** | Ringkasan Alokasi (Total Tunggakan, Nominal Pembayaran, Teralokasi, Sisa Tunggakan, Saldo Baru), tombol Proses Bayar Loket |
| **Transisi** | Modal Pembayaran Berhasil |

**Narasi**

- `04:13` Sebelum transaksi diproses, lakukan pemeriksaan akhir. Aplikasi tidak menampilkan jendela konfirmasi terpisah, sehingga bagian Ringkasan Alokasi inilah yang menjadi acuan pemeriksaan.
- `04:28` Pastikan Total Tunggakan dan Nominal Pembayaran sama-sama tiga ratus lima puluh ribu rupiah, seluruhnya Teralokasi, dan Sisa Tunggakan nol. Pastikan juga unit dan periode di bagian atas sudah benar, yaitu AL013 untuk Oktober 2026.
- `04:49` Jika semua data sudah benar, klik Proses Bayar Loket. Tombol ini langsung menyelesaikan transaksi, jadi pastikan pemeriksaan sudah dilakukan.

### Scene 8 — Transaksi Berhasil  `05:00`

| | |
|---|---|
| **Tampilan / menu** | Modal "Pembayaran Berhasil" → menu Cetak Kuitansi → tab Riwayat Kuitansi |
| **Aksi user** | Baca hasil; buka menu Cetak Kuitansi; Tutup; buka Riwayat Kuitansi |
| **UI yang disorot** | Nomor kuitansi, data penghuni & unit, periode, total, metode/kode loket/kasir, opsi cetak A4 & struk thermal, baris kuitansi baru |
| **Transisi** | Kartu penutup |

**Narasi**

- `05:03` Transaksi berhasil. Muncul notifikasi Pembayaran loket berhasil diproses, dan jendela Pembayaran Berhasil menampilkan nomor kuitansi beserta tanggal dan jam transaksi.
- `05:16` Di bawahnya tercantum nama penghuni dan alamat unit, periode 2026-10 dengan jumlah tagihan satu, total tagihan, denda, grand total, serta metode pembayaran, kode loket, dan nama kasir.
- `05:35` Klik Cetak Kuitansi untuk mencetak bukti pembayaran. Tersedia dua format, yaitu Kuitansi ukuran A4, dan Struk untuk printer thermal 80 milimeter.
- `05:48` Setelah bukti diserahkan kepada penghuni, klik Tutup. Kuitansi juga tersimpan di tab Riwayat Kuitansi. Cari berdasarkan nomor kuitansi atau nama penghuni, dan kuitansi dapat dicetak ulang kapan saja.
- `06:06` Sebagai ringkasan: buka menu Pembayaran, gunakan tab Proses Pembayaran, pilih unit dan satu bulan IPL, periksa tagihan, isi data pembayaran loket, cek Ringkasan Alokasi, lalu proses dan cetak kuitansinya.
- `06:23` Terima kasih telah menyimak. Selamat bertugas di loket.

