# Data Lengkap IPL — Grand Duta Residence

Seluruh data cluster, blok, penghuni, dan transaksi dari [final_revisi_1_per_cluster/](../final_revisi_1_per_cluster/README.md) dalam empat tabel yang saling terhubung. File sumber tidak diubah, dan 14 kolom asli setiap transaksi disalin apa adanya.

| File | Isi | Baris |
|---|---|---:|
| [Data_Lengkap_IPL.xlsx](Data_Lengkap_IPL.xlsx) | Keempat tabel dan catatan dalam satu workbook. Rekapnya memakai rumus, jadi koreksi di sheet Transaksi langsung memperbarui rekap. | - |
| [cluster.tsv](cluster.tsv) | Satu baris per cluster | 15 |
| [blok.tsv](blok.tsv) | Satu baris per blok atau kode | 64 |
| [penghuni.tsv](penghuni.tsv) | Satu baris per unit, dengan penghuni terakhir dan nama lainnya | 1.644 |
| [transaksi.tsv](transaksi.tsv) | Satu baris per pembayaran | 38.075 |

Untuk dibuka di Excel, pakai file `.xlsx`. Membuka `.tsv` langsung di Excel bisa mengubah teks seperti `Sep-15` menjadi tanggal, yaitu penyebab kerusakan di kolom `Bulan Tagihan`.

## Rekap per cluster

| Cluster | Kode | Jenis | Blok | Unit | Penghuni | Transaksi | Total nominal (Rp) | Di luar total (baris) | Bayar terakhir |
|---|---|---|---:|---:|---:|---:|---:|---:|---|
| [Alexandrite](../final_revisi_1_per_cluster/Alexandrite.md) | AX | Perumahan | 7 | 215 | 215 | 7.042 | 3.347.145.003 | 107 | 2026-10-07 |
| Ruko Alexandrite | AL- | Ruko | 0 | 0 | 0 | 0 | 0 | 0 | - |
| [Amber](../final_revisi_1_per_cluster/Amber.md) | AR | Perumahan | 4 | 43 | 43 | 270 | 122.066.500 | 0 | 2018-10-11 |
| [Ruko Amber](../final_revisi_1_per_cluster/Ruko-Amber.md) | AM | Ruko | 2 | 30 | 30 | 2.183 | 843.947.503 | 17 | 2026-10-06 |
| [Beryl](../final_revisi_1_per_cluster/Beryl.md) | B | Perumahan | 4 | 137 | 137 | 2.085 | 1.058.540.000 | 17 | 2026-10-07 |
| [Chrysocolla](../final_revisi_1_per_cluster/Chrysocolla.md) | C | Perumahan | 10 | 118 | 118 | 6.108 | 2.866.676.207 | 53 | 2026-10-07 |
| [Diamond](../final_revisi_1_per_cluster/Diamond.md) | DD | Perumahan | 3 | 41 | 41 | 3.061 | 1.250.257.500 | 24 | 2026-10-07 |
| [Dolomite](../final_revisi_1_per_cluster/Dolomite.md) | D (1–6) | Perumahan | 6 | 305 | 305 | 4.499 | 745.900.250 | 1 | 2024-03-22 |
| [Ruko Dolomite](../final_revisi_1_per_cluster/Ruko-Dolomite.md) | D (7–8) | Ruko | 2 | 18 | 18 | 175 | 127.400.000 | 0 | 2026-10-07 |
| [Emerald 48](../final_revisi_1_per_cluster/Emerald-48.md) | E (1–5) | Perumahan | 5 | 259 | 258 | 1.157 | 263.659.850 | 0 | 2023-12-01 |
| [Ruko Emerald](../final_revisi_1_per_cluster/Ruko-Emerald.md) | E (7) | Ruko | 1 | 36 | 36 | 809 | 558.380.000 | 8 | 2026-10-04 |
| [Garnet](../final_revisi_1_per_cluster/Garnet.md) | G | Perumahan | 2 | 192 | 192 | 2.307 | 466.052.500 | 0 | 2023-08-28 |
| [Jade](../final_revisi_1_per_cluster/Jade.md) | J | Perumahan | 9 | 167 | 167 | 5.842 | 1.439.413.240 | 0 | 2020-03-23 |
| [Ruko Jade Arcade](../final_revisi_1_per_cluster/Ruko-Jade-Arcade.md) | JA | Ruko | 1 | 27 | 27 | 1.689 | 653.326.263 | 17 | 2026-10-06 |
| [Belum Terklasifikasi](../final_revisi_1_per_cluster/Belum-Terklasifikasi.md) | - | - | 8 | 50 | 50 | 848 | 457.280.136 | 1 | 2026-09-19 |
| **Total** | | | **64** | **1.638** | **1.637** | **38.075** | **14.200.044.952** | **245** | |

Unit, transaksi, total nominal, dan baris di luar total sama persis dengan [rekap per cluster](../final_revisi_1_per_cluster/README.md#rekap-per-cluster), termasuk di tiap blok. Bedanya, kolom Blok di sini menghitung baris di `blok.tsv`: `JA` dihitung 1 dan delapan kode Belum Terklasifikasi dihitung 8.

## Perlu diputuskan

Tidak satu pun diterapkan pada data. Total tetap Rp 14.200.044.952 sampai ada keputusan.

| Temuan | Baris | Nilai (Rp) | Sudah di total? | Keterangan |
|---|---:|---:|---|---|
| Nominal tanpa pemisah | 241 | 78.800.000 | Belum | Tertulis mis. `225`; nilai ini angka tertulis dikali 1.000. |
| Nominal berawalan `Rp` | 4 | 1.900.000 | Belum | Tertulis mis. `Rp500.000,00`. |
| Nilai hanya di kolom `NML DBL` | 249 | 36.886.666 | Belum | **Temuan baru, belum ada di rekap per cluster.** `NOMINAL` kosong atau `-`, tetapi `NML DBL` berisi angka. Bisa pembayaran nyata, bisa nominal ganda; perlu konfirmasi keuangan. |
| Baris identik | 34 | 13.000.000 | Sudah | Semua 14 kolom sama dengan baris sebelumnya. Bila memang dobel, total berkurang sebesar ini. |
| Batal/salah yang bernominal | 2 | 425.000 | Sudah | Satu tertulis `Cancel` / `Dua Kwitansi`, satu hanya menyebut salah tulis pada kuitansi. |
| Cluster Belum Terklasifikasi | 848 | 457.280.136 | Sudah | Usulannya ada di [Klasifikasi Kode Blok, bagian 3](../final_revisi_1_klasifikasi_blok.md#3-perlu-ditinjau). Ruko Alexandrite tetap kosong karena `AL2` belum diputuskan. |
| Tanggal bayar tidak terpakai | 1.705 | 320.060.350 | Sudah | 1.625 kosong, 68 tidak terbaca, 12 meragukan. Tidak bisa masuk rekap per tahun. |

## Hubungan tabel

```text
cluster.tsv  --Cluster-->  blok.tsv  --ID Blok-->  penghuni.tsv  --ID Unit-->  transaksi.tsv
```

`ID Blok`, `ID Unit`, dan `ID Transaksi` hanya nomor urut untuk tabel ini, bukan ID database aplikasi.

## Cara membaca

**Penghuni**

- **`Penghuni Terakhir`** adalah nama pada pembayaran terakhir, mengikuti aturan `PenghuniData`. Isian seperti `Cancel` dan `Unit Available` diabaikan.
- **`Nama Lain`** menampung nama lain yang pernah tercatat. Hanya beda huruf besar/kecil yang digabung, jadi salah ketik dan pergantian pemilik sama-sama muncul. 1.158 unit punya lebih dari satu nama.
- **Kolom Penghuni pada rekap** menghitung unit yang punya nama, bukan jumlah orang. Hanya `E3 / 0` yang tanpa nama karena tertulis `NoName`.
- **`Status Hunian`** berasal dari `KET (1)` dan hanya ada di 1.029 unit. Terakhir dicatat 2016-10-23, jadi bukan kondisi saat ini. Lihat `Tgl Catatan Hunian`.
- **`Jenis Baris` = `Bukan unit`** menandai enam kelompok transaksi tanpa nomor unit yang sah. Tetap dicantumkan supaya jumlah transaksi utuh, tetapi tidak dihitung sebagai unit.

**Transaksi**

- **`Nominal (Rp)`** hanya berisi nilai yang masuk total. `Status Nominal` menjelaskan baris lainnya, dan nilainya, bila ada, ada di `Nilai di Luar Total (Rp)`.
- **`Tgl Bayar`** adalah hasil pembacaan `TGL Pembayaran` dengan `Status Tanggal`: `Tepat` (36.047), `Hanya bulan` (323, diisi tanggal 1), `Kosong`, `Tidak terbaca` (salah ketik seperti `20108` tidak ditebak), dan `Meragukan` (tahun 2026 yang tergeser saat konversi atau melewati hari ini).
- **`Bulan Tagihan (terbaca)`** memperbaiki bacaan 11.186 baris yang dikonversi menjadi tanggal: `2026-09-15` berarti September 2015, jadi ditulis `2015-09`. Polanya hari = tahun, dan 11.175 baris terbaca; sisanya, serta teks bebas, disalin apa adanya.
- **`NO. KWTANSI 2` berawalan `NN.2026`** sering ikut tercemar konversi, jadi jangan dipakai sebagai acuan tahun.
- **`Tanda`** hanya penanda untuk diperiksa. Tidak ada baris yang dihapus.
- **`Sumber`** menunjuk file dan nomor baris di [final_revisi_1_per_cluster/](../final_revisi_1_per_cluster/README.md).
