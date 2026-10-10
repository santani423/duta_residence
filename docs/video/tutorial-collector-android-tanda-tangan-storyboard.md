# Tutorial Video — Penugasan Kolektor & Tanda Tangan Penghuni (Aplikasi Android)

Video: `tutorial-collector-android-tanda-tangan.mp4` (1440×900, H.264 + AAC, narasi bahasa Indonesia, keterangan tertanam, durasi ±04:47).
Direkam dari aplikasi yang berjalan dengan data demo seeder pada database SQLite lokal terpisah (bukan database produksi):
- Bagian web: frontend React + API Laravel. Akun **Admin Estate** (`admin.estate`).
- Bagian Android (01:38–03:38): aplikasi Flutter *Duta Residence* (build release yang diarahkan ke server demo) di emulator Android 16. Akun **Kolektor** (`kolektor.budi`). Ditampilkan dalam bingkai ponsel dengan panel keterangan.

## 1. Aturan di aplikasi (dari kode)

| Area | Perilaku |
|---|---|
| Penugasan | Admin menugaskan unit lewat **Penugasan Kolektor** → **Tugaskan** (mis. *Unit Tertentu*). Unit langsung muncul di **Unit Saya** pada aplikasi kolektor. Kolektor hanya bisa membuka unit yang ditugaskan kepadanya. Penugasan unit tetap aktif; yang diselesaikan adalah kunjungannya. |
| Catat Kunjungan (Android) | Isian *Tujuan Kunjungan* (wajib), *Status Kunjungan* (Selesai / Tidak Ada Jawaban / Menolak / Dijadwalkan Ulang), *Bertemu Dengan*, *Hasil Kunjungan*, *Catatan Tambahan*. **Simpan Kunjungan** ikut mencatat lokasi GPS. |
| Menunggu tanda tangan | Kunjungan berstatus **Selesai** disimpan sebagai **Menunggu tanda tangan** (lifecycle `in_progress`). Selama menunggu, kunjungan belum dihitung di KPI kunjungan, kontak terakhir, maupun "Kunjungan Hari Ini". Status lain langsung final tanpa tanda tangan. |
| Tanda tangan penghuni | **Minta Tanda Tangan Penghuni** → penghuni menggambar di layar → **Konfirmasi** → pratinjau → **Gunakan Tanda Tangan**. Unggahan tanda tangan sekaligus menyelesaikan kunjungan di server (lifecycle `completed`, waktu selesai tercatat). Bila berkas gagal tersimpan, server membalas error dan kunjungan tetap menunggu agar bisa dicoba lagi. |
| Wajib tanda tangan | **Selesaikan Kunjungan** ditolak aplikasi sebelum ada tanda tangan ("Tanda tangan penghuni diperlukan…"). Server juga menolak penyelesaian tanpa tanda tangan (422). |
| Kunjungan tertunda | Keluar sebelum tanda tangan memunculkan dialog **Kunjungan belum selesai**. Di detail unit, kunjungan itu ditandai **Menunggu tanda tangan** (selalu ditampilkan walau ada kunjungan yang lebih baru). Mengetuknya membuka **Lanjutkan Kunjungan** untuk meminta tanda tangan. |
| Web admin & supervisor | Status kunjungan berbahasa Indonesia dan kolom **Tanda Tangan** (*Ditandatangani* / *Menunggu tanda tangan* / "—" untuk data lama). Tombol **Bukti** membuka modal **Bukti Kunjungan**: gambar tanda tangan, foto bukti, dan lokasi GPS (tautan Google Maps). Berkas dimuat lewat endpoint terautentikasi `GET visit-evidence/{id}/file` sesuai cakupan pengguna. Modal tersedia di Detail Kolektor (*Aktivitas Terakhir*), Detail Penghuni (*Kunjungan Collector*), dan detail kolektor versi supervisor. |

**Catatan temuan**
- Bila penghuni menolak tanda tangan, aplikasi belum menyediakan cara membatalkan atau mengubah kunjungan yang sudah disimpan. Kunjungan tetap *Menunggu tanda tangan*; kolektor bisa mencatat kunjungan baru dengan status *Menolak*.
- Foto bukti tetap opsional dan hanya lewat kamera.
- Aplikasi mengulang permintaan sekali saat koneksi timeout, sehingga unggahan yang lambat bisa tercatat sebagai dua tanda tangan. Ini tidak mengubah status kunjungan.
- Kunjungan lama (sebelum aturan ini) yang tidak bertanda tangan tetap dianggap selesai dan tampil netral ("—").

**Catatan rekaman**
- Keyboard layar dinonaktifkan selama perekaman, sehingga teks tampak langsung terisi. Di ponsel sungguhan, keyboard muncul seperti biasa.
- Lokasi GPS memakai titik simulasi emulator. Indikator sentuhan (*Show taps*) diaktifkan.
- Jeda menunggu yang panjang (memuat data) dipersingkat menjadi maksimal 1,6 detik; seluruh aksi dan tampilan lainnya berjalan apa adanya.

## 2. Skenario yang direkam (semua benar-benar dijalankan)

**Admin login → Penugasan Kolektor: tugaskan *Budi Santoso* ke unit *GA006* (Ibu Zaenab Wijayanti) → Aplikasi Android: login kolektor → Unit Saya → GA006 → Catat Kunjungan (status Selesai) → Simpan (Menunggu tanda tangan) → Selesaikan ditolak → keluar (dialog pengingat) → kunjungan tertunda di detail unit → Lanjutkan → tanda tangan penghuni → Gunakan Tanda Tangan (kunjungan selesai) → Selesaikan → Web: Data Kolektor → Aktivitas Terakhir (Ditandatangani) → modal Bukti (tanda tangan + GPS) → Detail Penghuni tab Kunjungan Collector**

## 3. Storyboard

### Scene 1 — Pembukaan & Alur Kerja  `00:00`

| | |
|---|---|
| **Tampilan / menu** | Kartu judul dan kartu *Alur Kerja* |
| **Aksi user** | Tidak ada interaksi; pengantar |
| **UI yang disorot** | Empat tahap: admin menugaskan unit → kolektor mencatat kunjungan → penghuni tanda tangan → admin memeriksa bukti |
| **Transisi** | Kartu bab 1 |

**Narasi**

- `00:00` Selamat datang. Tutorial ini menunjukkan alur penugasan kolektor sampai kunjungan penagihan selesai. Admin menugaskan unit lewat web, kolektor bekerja dengan aplikasi Android, dan setiap kunjungan baru dianggap selesai setelah penghuni membubuhkan tanda tangan di ponsel kolektor.
- `00:21` Alurnya ada empat tahap: admin menugaskan unit kepada kolektor, kolektor mencatat kunjungan di aplikasi, penghuni menandatangani di layar ponsel, lalu admin memeriksa bukti tanda tangan itu di web.

### Scene 2 — Admin Menugaskan Unit (Web)  `00:35`

| | |
|---|---|
| **Tampilan / menu** | `/admin/collectors/assignments` → tab **Tugaskan**, mode **Manual** → tab **Daftar Penugasan** |
| **Aksi user** | Login `admin.estate`; pilih kolektor *Budi Santoso*, jenis cakupan **Unit Tertentu**, unit **GA006** (Ibu Zaenab Wijayanti); isi catatan; **Simpan Penugasan**; filter daftar ke Budi |
| **UI yang disorot** | Kartu **Ringkasan Cakupan** (1 unit, belum dipegang kolektor lain, tunggakan Rp 5.220.000); baris GA006 berstatus Aktif |
| **Transisi** | Kartu bab 2 |

**Narasi**

- `00:35` Bagian satu: admin menugaskan unit kepada kolektor.
- `00:40` Admin estate masuk ke aplikasi web dengan akunnya.
- `00:51` Buka menu Penugasan Kolektor, tab Tugaskan, mode Manual. Pilih kolektor Budi Santoso dan jenis cakupan Unit Tertentu.
- `01:01` Lalu pilih unit GA006 di Cluster Gardenia, milik Ibu Zaenab Wijayanti.
- `01:08` Ringkasan Cakupan memastikan unit ini belum dipegang kolektor lain dan menunjukkan total tunggakannya. Periksa ringkasan ini sebelum menyimpan.
- `01:18` Klik Simpan Penugasan. Penugasan langsung aktif, dan unit GA006 kini menjadi tanggung jawab Budi.

### Scene 3 — Masuk ke Bagian Android  `01:32`

| | |
|---|---|
| **Tampilan / menu** | Kartu *2 · Kolektor di Aplikasi Android* |
| **Aksi user** | Tidak ada interaksi |
| **UI yang disorot** | Catat kunjungan · Minta tanda tangan penghuni · Selesaikan kunjungan |
| **Transisi** | Layar ponsel kolektor |

**Narasi**

- `01:32` Bagian dua: kolektor bekerja dengan aplikasi Android di ponselnya.

### Scene 4 — Kolektor Login & Menemukan Unit (Android)  `01:38`

| | |
|---|---|
| **Tampilan / menu** | Aplikasi Duta Residence: Login → **Beranda** → **Unit Saya** → detail unit GA006 |
| **Aksi user** | Login `kolektor.budi`; buka **Unit Saya**; cari "GA006"; buka unitnya |
| **UI yang disorot** | Unit baru dari admin langsung muncul; **Informasi Unit** (penghuni, telepon, total tunggakan); "Belum ada kunjungan tercatat." |
| **Transisi** | Ketuk **Catat Kunjungan** |

**Narasi**

- `01:38` Kolektor membuka aplikasi Duta Residence di ponselnya, lalu masuk dengan username dan password akun kolektor.
- `01:49` Beranda menampilkan performa bulan ini serta akses cepat ke Unit Saya, Rute, dan pengingat penagihan.
- `01:57` Buka Unit Saya. Unit yang baru ditugaskan admin langsung muncul di sini. Ketik GA006 di kolom pencarian.
- `02:07` Detail unit menampilkan nama penghuni, nomor telepon, dan total tunggakan. Kunjungan dicatat lewat tombol Catat Kunjungan.

### Scene 5 — Mencatat Kunjungan  `02:17`

| | |
|---|---|
| **Tampilan / menu** | Layar **Catat Kunjungan** |
| **Aksi user** | Isi *Tujuan Kunjungan* "Penagihan tunggakan IPL", *Bertemu Dengan* "Ibu Zaenab", *Hasil Kunjungan*; status **Selesai**; ketuk **Simpan Kunjungan** |
| **UI yang disorot** | Snackbar "Kunjungan tersimpan. Minta tanda tangan penghuni untuk menyelesaikannya."; kartu kuning **Menunggu tanda tangan penghuni.** |
| **Transisi** | Coba selesaikan |

**Narasi**

- `02:17` Isi tujuan kunjungan, orang yang ditemui, dan hasil kunjungan. Status Selesai dipakai karena kolektor bertemu langsung dengan penghuni.
- `02:27` Ketuk Simpan Kunjungan. Lokasi GPS ikut tercatat. Kunjungan tersimpan dengan status Menunggu tanda tangan dan belum dihitung selesai.

### Scene 6 — Aturan Wajib Tanda Tangan & Kunjungan Tertunda  `02:38`

| | |
|---|---|
| **Tampilan / menu** | Form kunjungan → dialog keluar → detail unit → **Lanjutkan Kunjungan** |
| **Aksi user** | Ketuk **Selesaikan Kunjungan** sebelum tanda tangan; tekan Kembali → **Keluar**; ketuk kunjungan yang tertunda |
| **UI yang disorot** | Pesan "Tanda tangan penghuni diperlukan…"; dialog **Kunjungan belum selesai**; chip **Menunggu tanda tangan** di *Kunjungan Terakhir* |
| **Transisi** | Minta tanda tangan |

**Narasi**

- `02:38` Bila kolektor mencoba menyelesaikan kunjungan sebelum penghuni tanda tangan, aplikasi menolak dan meminta tanda tangan terlebih dahulu.
- `02:48` Bila kolektor keluar sebelum tanda tangan, aplikasi mengingatkan. Kunjungan tetap tersimpan sebagai Menunggu tanda tangan di detail unit.
- `02:58` Kunjungan yang tertunda bisa dilanjutkan kapan saja. Ketuk kunjungan itu untuk membukanya kembali.

### Scene 7 — Tanda Tangan Penghuni & Menyelesaikan Kunjungan  `03:06`

| | |
|---|---|
| **Tampilan / menu** | Layar **Tanda Tangan Penghuni** → pratinjau → form → detail unit |
| **Aksi user** | Penghuni menggambar tanda tangan; **Konfirmasi** → **Gunakan Tanda Tangan**; **Selesaikan Kunjungan** |
| **UI yang disorot** | Kartu hijau "Tanda tangan penghuni sudah tersimpan. Kunjungan selesai."; kunjungan berstatus **Selesai** dengan centang |
| **Transisi** | Kartu bab 3 |

**Narasi**

- `03:06` Ketuk Minta Tanda Tangan Penghuni, lalu serahkan ponsel kepada penghuni. Penghuni membubuhkan tanda tangan di layar.
- `03:16` Ketuk Konfirmasi, periksa pratinjau, lalu Gunakan Tanda Tangan. Tanda tangan terunggah dan kunjungan otomatis selesai.
- `03:26` Ketuk Selesaikan Kunjungan. Di detail unit, kunjungan kini berstatus Selesai dengan tanda centang tanda tangan.

### Scene 8 — Admin Memeriksa Bukti (Web)  `03:38`

| | |
|---|---|
| **Tampilan / menu** | Data Kolektor → *Budi Santoso* → tab **Aktivitas Terakhir** → modal **Bukti Kunjungan** → detail penghuni tab **Kunjungan Collector** |
| **Aksi user** | Buka detail kolektor; klik **Bukti** pada baris GA006; buka detail penghuni |
| **UI yang disorot** | Kolom **Tanda Tangan: Ditandatangani**; gambar tanda tangan penghuni; **Lokasi GPS** dan waktunya; tombol **Bukti (n)** |
| **Transisi** | Penutup |

**Narasi**

- `03:38` Bagian tiga: admin memeriksa bukti kunjungan di web.
- `03:42` Admin membuka menu Data Kolektor, lalu memilih Budi Santoso.
- `03:59` Di tab Aktivitas Terakhir, kunjungan ke GA006 tercatat Selesai dan Ditandatangani. Kunjungan yang belum ditandatangani berstatus Menunggu tanda tangan dan belum dihitung sebagai kunjungan selesai.
- `04:14` Klik Bukti untuk membuka tanda tangan penghuni, lokasi GPS saat kunjungan, dan waktu pengambilannya.
- `04:22` Bukti yang sama juga tersedia di halaman detail penghuni, tab Kunjungan Collector.

### Scene 9 — Penutup  `04:29`

| | |
|---|---|
| **Tampilan / menu** | Kartu *Selesai* |
| **Aksi user** | Tidak ada interaksi |
| **UI yang disorot** | Kunjungan selesai = penghuni sudah tanda tangan di aplikasi Android kolektor |
| **Transisi** | Selesai |

**Narasi**

- `04:29` Itulah alur penugasan kolektor dengan aplikasi Android. Kunjungan baru dihitung selesai setelah penghuni menandatangani di ponsel kolektor, dan kunjungan yang tertunda bisa dilanjutkan kapan saja dari detail unit. Terima kasih.

