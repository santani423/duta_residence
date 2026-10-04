# Tutorial Skema Pembayaran: Pengajuan Diskon & Penghapusan Denda

Video: `tutorial-skema-diskon-denda.mp4` (1920×1080, H.264 + AAC, narasi bahasa Indonesia, subtitle tertanam).
Direkam dari aplikasi yang berjalan (data demo seeder, database SQLite lokal) dengan tiga akun: **Loket** (`loket`), **Admin Estate** (`admin.estate`), dan **Super Admin** (`superadmin`). Pergantian akun dilakukan di luar rekaman dan ditandai kartu "Masuk sebagai …".

## 1. Aturan di aplikasi (dari kode)

| Aturan | Sumber |
|---|---|
| Loket (dan Admin/Super Admin) dapat mengajukan skema: pilih unit & tagihan, keringanan denda per tagihan (atau "Hapus seluruh denda yang dipilih"), diskon pokok Nominal/Persentase atas total sisa pokok, alasan wajib | `PaymentSchemesPage` SubmitDrawer, izin `payment-schemes.submit` |
| Pengajuan berstatus **Menunggu Admin**; semua pemegang izin `payment-schemes.approve` (Admin Estate, Super Admin, root) mendapat notifikasi | `PaymentSchemeNotifier::submitted` |
| Admin dapat **Setujui** apa adanya, **Sesuaikan & Setujui** (ubah diskon, keringanan denda per bulan, "Tolak bulan ini"), atau **Tolak** dengan alasan; usulan asli loket tetap tercatat | `PaymentSchemeService::approve/reject`, ApproveDrawer |
| **Batas diskon Admin**: diskon per tagihan (diskon lama + diskon skema) tidak boleh melebihi *Maksimum Diskon Admin* % dari nominal tagihan; bawaan **30%**, diatur Super Admin di menu **Batas Diskon Admin** | `DiscountService`, `DiscountSetting::DEFAULT_MAXIMUM_ADMIN_DISCOUNT` |
| Skema di atas batas: tag "Di atas batas Admin"; bagi Admin tombol **Setujui** dan **Tolak** nonaktif — Admin hanya bisa menurunkan diskon hingga batas lalu menyetujui; selain itu diputuskan **Super Admin** (tidak dibatasi) | `exceedsAdminLimit`, `reject()` guard, UI |
| Keringanan/penghapusan denda **tidak** dibatasi persentase — batas hanya berlaku untuk diskon pokok | `approve()` hanya mengecek diskon |
| Keputusan (setuju/tolak) dinotifikasikan ke pengaju | `PaymentSchemeNotifier::approved/rejected` |
| Selama Pending, jangan bayar tagihan terkait — skema otomatis dibatalkan bila kondisi tagihan berubah | Konfirmasi pengajuan, `ensureFresh` |
| Setelah disetujui, tagihan skema dibayar bersamaan lewat tombol **Bayar** (Bayar Skema) | PaySchemeDrawer |

## 2. Skenario yang direkam (semua benar-benar dijalankan)

| # | Unit | Usulan loket (Jan–Apr 2026) | Keputusan | Hasil |
|---|---|---|---|---|
| 1 | AL015 — Dustin Pfannerstill | Diskon 10% + hapus seluruh denda → Rp 1.260.000 | Admin: Setujui apa adanya | Disetujui; dibayar loket, kuitansi GD.2026.10.000001 |
| 2 | GA026 — Prof. George Grant MD | Diskon 25% + hapus seluruh denda → Rp 900.000 | Admin: diskon jadi 15%, April ditolak → Simpan perubahan & Setujui | Disetujui, tag "Diubah Admin", 3 bulan + 1 ditolak, Rp 765.000 |
| 3 | NI001 — Myriam Gutkowski | Diskon 50% + hapus seluruh denda → Rp 630.000 | Admin: Setujui & Tolak nonaktif (di atas batas 30%) → Super Admin: Setujui | Disetujui oleh Super Admin |

## 3. Storyboard

### Scene 0 — Pembukaan & Alur Persetujuan  `00:00`

| | |
|---|---|
| **Tampilan / menu** | Kartu judul + panel alur |
| **Aksi user** | Tidak ada interaksi |
| **UI yang disorot** | Alur: Loket mengajukan → Admin menyetujui/menyesuaikan/menolak → Super Admin bila di atas batas |
| **Transisi** | Halaman Batas Diskon Admin (Super Admin) |

**Narasi**

- `00:00` Selamat datang. Video ini menjelaskan cara mengajukan diskon dan penghapusan denda untuk penghuni melalui fitur Skema Pembayaran, beserta alur persetujuannya.
- `00:13` Setiap pengajuan dari loket harus disetujui Admin. Admin dapat menyetujui seluruh pengajuan apa adanya, melakukan penyesuaian lalu menyetujui, atau menolak.
- `00:26` Jika diskon melebihi batas maksimum Admin yang ditetapkan Super Admin, secara bawaan tiga puluh persen, maka pengajuan tersebut harus diputuskan oleh Super Admin.
- `00:39` Kita akan mempraktikkan tiga skenario: pengajuan yang disetujui apa adanya, pengajuan yang disesuaikan Admin, dan pengajuan di atas batas yang disetujui Super Admin.

### Scene 1 — Batas Diskon Admin (Super Admin)  `00:53`

| | |
|---|---|
| **Tampilan / menu** | Super Admin — Batas Diskon Admin |
| **Aksi user** | Buka menu Batas Diskon Admin; tunjukkan tipe input & maksimum 30% |
| **UI yang disorot** | Tipe Input Diskon Admin, Maksimum Diskon Admin (%), info berlaku |
| **Transisi** | Ganti peran ke Loket |

**Narasi**

- `00:56` Pertama, kita lihat pengaturan batas diskon. Pengaturan ini hanya dapat diakses Super Admin, melalui menu Batas Diskon Admin.
- `01:06` Maksimum Diskon Admin saat ini tiga puluh persen. Artinya, Admin hanya boleh menyetujui diskon paling besar tiga puluh persen dari nominal setiap tagihan. Super Admin sendiri tidak dibatasi.
- `01:21` Super Admin juga menentukan tipe input diskon Admin, persentase atau nominal. Perubahan berlaku untuk pengajuan berikutnya. Pada tutorial ini, pengaturan dibiarkan tiga puluh persen.

### Scene 2 — Loket Mengajukan Skema (Skenario 1)  `01:40`

| | |
|---|---|
| **Tampilan / menu** | Loket — Skema Pembayaran → drawer Ajukan Skema Pembayaran |
| **Aksi user** | Pilih unit AL015, centang Jan–Apr 2026, hapus seluruh denda, diskon 10%, isi alasan, Ajukan ke Admin |
| **UI yang disorot** | Menu Skema Pembayaran, 5 langkah form, Ringkasan, konfirmasi, status Menunggu Admin |
| **Transisi** | Pengajuan skenario 2 |

**Narasi**

- `01:43` Petugas loket membuka menu Skema Pembayaran. Halaman ini berisi seluruh pengajuan beserta statusnya.
- `01:51` Klik tombol Ajukan Skema. Form pengajuan terbuka. Langkah pertama, pilih unit penghuni yang mengajukan keringanan. Ketik ID unit atau nama penghuni, lalu pilih dari daftar.
- `02:05` Langkah kedua, centang tagihan yang diajukan. Pada contoh ini, empat bulan tertunggak dari Januari sampai April 2026. Setiap tagihan menampilkan sisa pokok dan dendanya.
- `02:20` Untuk penghapusan denda, isi kolom Keringanan Denda pada setiap tagihan, atau klik Hapus pada baris tertentu. Untuk menghapus seluruh denda sekaligus, klik Hapus seluruh denda yang dipilih. Kolom Denda Akhir kini menjadi nol.
- `02:37` Langkah ketiga, diskon pokok. Pilih jenis diskon Nominal atau Persentase. Pada contoh ini, pilih Persentase dan isi 10 persen dari total sisa pokok.
- `02:50` Langkah keempat, periksa Ringkasan: pokok awal, diskon pokok beserta persentasenya, denda awal, keringanan denda, dan total yang harus dibayar penghuni jika skema disetujui.
- `03:04` Langkah kelima, isi alasan pengajuan. Alasan wajib diisi dan akan dibaca Admin saat meninjau.
- `03:12` Klik Ajukan ke Admin. Muncul konfirmasi yang menampilkan total yang harus dibayar. Selama skema menunggu persetujuan, jangan proses pembayaran untuk tagihan ini, karena skema akan otomatis dibatalkan. Klik Ajukan.
- `03:29` Pengajuan berhasil dikirim. Skema muncul di daftar dengan status Menunggu Admin, dan Admin menerima notifikasi.

### Scene 3 — Pengajuan Skenario 2 & 3  `03:40`

| | |
|---|---|
| **Tampilan / menu** | Loket — Ajukan Skema untuk GA026 (25%) dan NI001 (50%) |
| **Aksi user** | Dua pengajuan lagi dengan langkah yang sama |
| **UI yang disorot** | Daftar skema; tag "Di atas batas Admin" pada NI001 |
| **Transisi** | Ganti peran ke Admin |

**Narasi**

- `03:43` Dengan langkah yang sama, kita ajukan dua skema lagi. Skenario kedua untuk unit GA026, dengan diskon dua puluh lima persen dan penghapusan seluruh denda.
- `03:56` Klik Ajukan Skema, lalu pilih unit GA026.
- `04:05` Centang tagihan Januari sampai April 2026.
- `04:12` Klik Hapus seluruh denda yang dipilih untuk mengajukan penghapusan denda.
- `04:19` Pilih Persentase dan isi diskon 25 persen.
- `04:27` Ringkasan langsung menghitung total yang harus dibayar jika disetujui.
- `04:33` Isi alasan pengajuan.
- `04:38` Klik Ajukan ke Admin, lalu Ajukan pada konfirmasi.
- `04:44` Skenario ketiga untuk unit NI001, dengan diskon lima puluh persen, melebihi batas Admin tiga puluh persen.
- `04:54` Klik Ajukan Skema, lalu pilih unit NI001.
- `05:04` Centang tagihan Januari sampai April 2026.
- `05:11` Klik Hapus seluruh denda yang dipilih untuk mengajukan penghapusan denda.
- `05:17` Pilih Persentase dan isi diskon 50 persen.
- `05:25` Ringkasan langsung menghitung total yang harus dibayar jika disetujui.
- `05:31` Isi alasan pengajuan.
- `05:38` Klik Ajukan ke Admin, lalu Ajukan pada konfirmasi.
- `05:44` Perhatikan daftar skema. Pengajuan NI001 otomatis diberi tanda Di atas batas Admin, karena diskonnya melebihi tiga puluh persen.

### Scene 4 — Admin Menyetujui Seluruh Pengajuan  `06:00`

| | |
|---|---|
| **Tampilan / menu** | Admin — notifikasi → drawer "Tinjau & Setujui Skema" |
| **Aksi user** | Buka notifikasi; Tinjau & Setujui AL015; Setujui tanpa perubahan |
| **UI yang disorot** | Lonceng notifikasi, info pengajuan, tabel per bulan, Ringkasan "Belum ada perubahan", tombol Setujui, status Disetujui |
| **Transisi** | Skenario 2 |

**Narasi**

- `06:03` Admin menerima notifikasi setiap ada pengajuan skema baru. Klik ikon lonceng untuk melihatnya.
- `06:11` Pada halaman Skema Pembayaran, klik Tinjau dan Setujui pada pengajuan unit AL015.
- `06:19` Bagian atas menampilkan unit, pengaju, dan alasannya. Tabel per bulan menunjukkan sisa pokok, denda, usulan keringanan dari loket, dan denda akhir.
- `06:32` Di bagian Diskon pokok terlihat usulan loket sepuluh persen, masih di bawah batas Admin tiga puluh persen. Ringkasan menyatakan belum ada perubahan dari usulan loket.
- `06:46` Untuk menyetujui seluruh pengajuan apa adanya, klik Setujui. Diskon dan penghapusan denda langsung diterapkan pada tagihan, dan status skema menjadi Disetujui.

### Scene 5 — Admin Melakukan Penyesuaian  `07:00`

| | |
|---|---|
| **Tampilan / menu** | Admin — drawer Tinjau & Setujui GA026 |
| **Aksi user** | Tolak bulan April; ubah diskon 25% → 15%; isi catatan; Simpan perubahan & Setujui |
| **UI yang disorot** | Tombol "Tolak bulan ini", kolom Persentase, info batas Admin, peringatan "Berbeda dari usulan loket", tag Diubah Admin |
| **Transisi** | Skenario 3 |

**Narasi**

- `07:03` Skenario kedua. Buka Tinjau dan Setujui pada pengajuan unit GA026. Kali ini Admin akan menyesuaikan pengajuan sebelum menyetujuinya.
- `07:15` Admin dapat mengeluarkan bulan tertentu dari skema. Klik Tolak bulan ini pada April 2026. Bulan yang ditolak kembali menjadi tagihan biasa tanpa diskon maupun keringanan denda.
- `07:30` Admin juga dapat mengubah keringanan denda per bulan, dan mengubah diskon pokok. Pada contoh ini, diskon diturunkan dari dua puluh lima menjadi lima belas persen.
- `07:43` Ringkasan memperlihatkan total baru dan peringatan bahwa hasilnya berbeda dari usulan loket. Usulan asli loket tetap tercatat untuk jejak audit.
- `07:54` Tambahkan catatan Admin bila perlu, lalu klik Simpan perubahan dan Setujui.
- `08:04` Kolom Tagihan pada skema GA026 kini menunjukkan tiga bulan dengan satu bulan ditolak. Geser ke kanan: statusnya Disetujui dengan tanda Diubah Admin.

### Scene 6 — Pengajuan di Atas Batas Admin  `08:18`

| | |
|---|---|
| **Tampilan / menu** | Admin — daftar & drawer Tinjau NI001 |
| **Aksi user** | Tunjukkan Tolak nonaktif; buka Tinjau; baca peringatan; Setujui nonaktif; tutup |
| **UI yang disorot** | Tag Di atas batas Admin, tombol Tolak nonaktif, peringatan batas, teks batas merah, tombol Setujui nonaktif |
| **Transisi** | Ganti peran ke Super Admin |

**Narasi**

- `08:21` Skenario ketiga, unit NI001 dengan diskon lima puluh persen. Perhatikan tombol Tolak tidak aktif untuk Admin, karena skema di atas batas diskon Admin hanya dapat ditolak oleh Super Admin.
- `08:38` Buka Tinjau dan Setujui. Muncul peringatan bahwa usulan loket melebihi batas diskon Admin tiga puluh persen, sehingga Admin tidak dapat menyetujui atau menolaknya apa adanya.
- `08:51` Tombol Setujui juga tidak aktif, dan bagian Diskon pokok menandai bahwa diskon melebihi batas maksimum Admin.
- `09:01` Admin punya dua pilihan: menurunkan diskon hingga maksimal tiga puluh persen lalu menyetujui, atau membiarkan skema ini menunggu keputusan Super Admin, yang sudah menerima notifikasi pengajuan. Pada contoh ini, Admin menutup jendela tanpa mengubah apa pun.

### Scene 7 — Super Admin Menyetujui  `09:24`

| | |
|---|---|
| **Tampilan / menu** | Super Admin — Tinjau & Setujui NI001 |
| **Aksi user** | Buka Tinjau; Setujui diskon 50% |
| **UI yang disorot** | Teks "Anda Super Admin: diskon tidak dibatasi", tombol Setujui aktif, status Disetujui |
| **Transisi** | Ganti peran ke Loket |

**Narasi**

- `09:27` Super Admin membuka Skema Pembayaran. Pengajuan NI001 masih Menunggu Admin dengan tanda Di atas batas Admin. Klik Tinjau dan Setujui.
- `09:39` Bagi Super Admin, diskon tidak dibatasi, seperti yang tertulis di bagian Diskon pokok. Tombol Setujui aktif.
- `09:49` Klik Setujui. Diskon lima puluh persen dan penghapusan denda diterapkan, dan status skema menjadi Disetujui. Super Admin juga dapat menolak pengajuan ini bila tidak sesuai kebijakan.

### Scene 8 — Loket Menerima Hasil & Memproses Pembayaran  `10:08`

| | |
|---|---|
| **Tampilan / menu** | Loket — notifikasi, Detail skema GA026, drawer Bayar Skema AL015 |
| **Aksi user** | Buka notifikasi; lihat Detail GA026; Bayar skema AL015 via Loket |
| **UI yang disorot** | Notifikasi keputusan, info "Diubah oleh Admin", bulan ditolak, Ringkasan Perhitungan Skema, Proses Bayar Skema, kuitansi |
| **Transisi** | Ringkasan & penutup |

**Narasi**

- `10:11` Loket menerima notifikasi setiap kali pengajuannya disetujui atau ditolak.
- `10:19` Klik Detail pada skema GA026. Terlihat bahwa skema ini diubah oleh Admin saat persetujuan, beserta catatan Admin dan bulan yang ditolak.
- `10:34` Setelah skema disetujui, penghuni dapat membayar. Klik Bayar pada skema AL015. Seluruh tagihan dalam satu skema dibayar bersamaan, dengan nominal akhir yang sudah dipotong diskon dan keringanan denda.
- `10:51` Isi data pembayaran loket seperti biasa, periksa Ringkasan Alokasi, lalu klik Proses Bayar Skema. Kuitansi terbit untuk seluruh tagihan dalam skema.
- `11:04` Sebagai ringkasan: loket mengajukan skema berisi tagihan, keringanan denda, diskon, dan alasan. Admin menyetujui apa adanya, menyesuaikan lalu menyetujui, atau menolak. Diskon di atas batas Admin diputuskan oleh Super Admin. Setelah disetujui, loket memproses pembayaran skema.
- `11:27` Terima kasih telah menyimak.

