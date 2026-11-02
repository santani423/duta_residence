# Klasifikasi Kode Blok — Buku IPL

Daftar semua kode blok di buku pembayaran IPL beserta cluster, nomor blok, dan jenis propertinya.

- **Sumber:** kolom `block` dan `no unit` di [final_revisi_1.md](final_revisi_1.md) (Sheet1). Kode ditulis persis seperti di sumber; file sumber tidak diubah.
- **Cakupan:** hanya buku IPL. `database/data/pelanggan.csv` dan database aktif belum termasuk, jadi kode yang hanya ada di CSV (`AL-`, `B1`, `DR`, `A10`) tidak tercantum.
- **Dibuat:** 2 November 2026.

## 1. Ringkasan

| Status | Kode | Transaksi |
|---|---:|---:|
| Valid | 55 | 35.505 |
| Format Tidak Valid | 18 | 1.722 |
| Tidak Teridentifikasi (Ambigu) | 3 | 585 |
| Tidak Teridentifikasi | 8 | 263 |
| **Total** | **84** | **38.075** |

| Jenis Properti | Unit unik |
|---|---:|
| Perumahan | 1.477 |
| Ruko | 111 |
| Belum diklasifikasikan | 50 |
| **Total** | **1.638** |

## 2. Aturan klasifikasi

Kode blok = kode cluster + nomor blok, mis. `AX3` = Alexandrite blok 3.

| Kode cluster | Nama cluster | Nomor blok | Jenis properti |
|---|---|---|---|
| AX | Alexandrite | semua | Perumahan |
| AL- | Ruko Alexandrite | semua | Ruko |
| AR | Amber | semua | Perumahan |
| AM | Ruko Amber | semua | Ruko |
| B | Beryl | semua | Perumahan |
| C | Chrysocolla | semua | Perumahan |
| DD | Diamond | semua | Perumahan |
| D | Dolomite | 1–6 | Perumahan |
| D | Ruko Dolomite | 7–8 | Ruko |
| E | Emerald 48 | 1–5 | Perumahan |
| E | Ruko Emerald | 7 | Ruko |
| G | Garnet | semua | Perumahan |
| J | Jade | semua | Perumahan |
| JA | Ruko Jade Arcade | semua | Ruko |

| Status | Arti |
|---|---|
| Valid | Huruf besar, kode cluster + nomor blok, dan nomornya sesuai aturan. Nomor tanpa nol di depan (`AX3`) tetap valid. |
| Format Tidak Valid | Cluster diketahui, tetapi kodenya memakai huruf kecil atau tidak punya nomor blok. |
| Tidak Teridentifikasi (Ambigu) | Mirip kode yang dikenal, tetapi tidak cocok dengan aturan, sehingga perlu keputusan. |
| Tidak Teridentifikasi | Tidak ada pada aturan. |

## 3. Perlu ditinjau

Status di bawah belum diubah sampai ada keputusan. Nominal berasal dari [final_revisi_1_tersusun.md](final_revisi_1_tersusun.md).

| Kode | Transaksi | Nominal (Rp) | Temuan | Usulan |
|---|---:|---:|---|---|
| `AL2`, `Al2` | 500 | 272.700.005 | Aturan hanya mengenal `AL-`. Nomor unit dan pemiliknya sama dengan 14 unit `AL-` di `pelanggan.csv`. | Jadikan Ruko Alexandrite blok 2 |
| `E6` | 85 | 19.527.500 | Aturan hanya mengatur E1–5 dan E7. `pelanggan.csv` mencatat `E6` sebagai Emerald 48, dan `config/cluster_codes.php` menerima E1–6. | Jadikan Emerald 48 blok 6 |
| `JA`, `ja` | 1.689 | 653.326.263 | Cluster jelas, tetapi tidak ada nomor blok. | Terima sebagai Ruko Jade Arcade tanpa nomor blok |
| `DR3` | 2 | 18.000.000 | Atas nama Yayasan Al Amanah, no. unit `6,7,8,9`. Salah satu baris `DR` di `pelanggan.csv` ber-cluster Diamond, tetapi kode Diamond adalah `DD`. | Konfirmasi ke pengelola |
| `Unit` | 9 | 141.177.631 | Tertulis `Unit`/`Stock` atas nama tiga PT. | Bukan blok; konfirmasi ke keuangan |
| `Cafe`, `cafe` | 4 | 2.500.000 | Malca Cafe tanpa nomor unit. | Konfirmasi lokasi ke pengelola |
| `??` | 3 | 850.000 | Blok dan nomor unit tertulis `??`. | Telusuri dari kuitansi |
| (kosong) | 234 | 2.525.000 | 7 baris bernominal; 227 lainnya tanpa nominal, kebanyakan batal atau salah. | Telusuri 7 baris bernominal |
| `Cancel`, `cancel` | 11 | 0 | Kuitansi batal. | Tetap di luar klasifikasi |

## 4. Tabel klasifikasi

| Kode Blok | Nama Cluster | Nomor Blok | Jenis Properti | Status | Unit | Transaksi |
|---|---|---:|---|---|---:|---:|
| (kosong) | - | - | - | Tidak Teridentifikasi | 0 | 234 |
| ?? | - | - | - | Tidak Teridentifikasi | 0 | 3 |
| AL2 | - | - | - | Tidak Teridentifikasi (Ambigu) | 14 | 499 |
| Al2 | - | - | - | Tidak Teridentifikasi (Ambigu) | 1 | 1 |
| AM1 | Ruko Amber | 1 | Ruko | Valid | 14 | 1.053 |
| Am1 | Ruko Amber | 1 | Ruko | Format Tidak Valid | 5 | 6 |
| AM2 | Ruko Amber | 2 | Ruko | Valid | 16 | 1.119 |
| Am2 | Ruko Amber | 2 | Ruko | Format Tidak Valid | 2 | 2 |
| am2 | Ruko Amber | 2 | Ruko | Format Tidak Valid | 3 | 3 |
| AR1 | Amber | 1 | Perumahan | Valid | 8 | 45 |
| AR2 | Amber | 2 | Perumahan | Valid | 9 | 49 |
| AR3 | Amber | 3 | Perumahan | Valid | 9 | 71 |
| AR5 | Amber | 5 | Perumahan | Valid | 17 | 105 |
| AX2 | Alexandrite | 2 | Perumahan | Valid | 2 | 52 |
| AX3 | Alexandrite | 3 | Perumahan | Valid | 41 | 1.677 |
| Ax3 | Alexandrite | 3 | Perumahan | Format Tidak Valid | 2 | 2 |
| AX5 | Alexandrite | 5 | Perumahan | Valid | 67 | 2.331 |
| ax5 | Alexandrite | 5 | Perumahan | Format Tidak Valid | 2 | 2 |
| Ax5 | Alexandrite | 5 | Perumahan | Format Tidak Valid | 5 | 5 |
| AX6 | Alexandrite | 6 | Perumahan | Valid | 32 | 421 |
| AX7 | Alexandrite | 7 | Perumahan | Valid | 30 | 780 |
| AX8 | Alexandrite | 8 | Perumahan | Valid | 41 | 1.606 |
| ax8 | Alexandrite | 8 | Perumahan | Format Tidak Valid | 1 | 1 |
| Ax8 | Alexandrite | 8 | Perumahan | Format Tidak Valid | 1 | 1 |
| AX9 | Alexandrite | 9 | Perumahan | Valid | 2 | 164 |
| B7 | Beryl | 7 | Perumahan | Valid | 8 | 62 |
| B8 | Beryl | 8 | Perumahan | Valid | 32 | 449 |
| B9 | Beryl | 9 | Perumahan | Valid | 45 | 694 |
| B10 | Beryl | 10 | Perumahan | Valid | 52 | 880 |
| C1 | Chrysocolla | 1 | Perumahan | Valid | 17 | 913 |
| C2 | Chrysocolla | 2 | Perumahan | Valid | 10 | 355 |
| c2 | Chrysocolla | 2 | Perumahan | Format Tidak Valid | 1 | 1 |
| C3 | Chrysocolla | 3 | Perumahan | Valid | 10 | 374 |
| c3 | Chrysocolla | 3 | Perumahan | Format Tidak Valid | 1 | 1 |
| C5 | Chrysocolla | 5 | Perumahan | Valid | 9 | 449 |
| c5 | Chrysocolla | 5 | Perumahan | Format Tidak Valid | 1 | 1 |
| C6 | Chrysocolla | 6 | Perumahan | Valid | 18 | 1.001 |
| c6 | Chrysocolla | 6 | Perumahan | Format Tidak Valid | 3 | 3 |
| C7 | Chrysocolla | 7 | Perumahan | Valid | 15 | 634 |
| C8 | Chrysocolla | 8 | Perumahan | Valid | 11 | 685 |
| c8 | Chrysocolla | 8 | Perumahan | Format Tidak Valid | 2 | 2 |
| C9 | Chrysocolla | 9 | Perumahan | Valid | 11 | 658 |
| C10 | Chrysocolla | 10 | Perumahan | Valid | 6 | 301 |
| C11 | Chrysocolla | 11 | Perumahan | Valid | 11 | 729 |
| c11 | Chrysocolla | 11 | Perumahan | Format Tidak Valid | 1 | 1 |
| Cafe | - | - | - | Tidak Teridentifikasi | 0 | 3 |
| cafe | - | - | - | Tidak Teridentifikasi | 0 | 1 |
| Cancel | - | - | - | Tidak Teridentifikasi | 0 | 9 |
| cancel | - | - | - | Tidak Teridentifikasi | 0 | 2 |
| D1 | Dolomite | 1 | Perumahan | Valid | 84 | 1.365 |
| D2 | Dolomite | 2 | Perumahan | Valid | 72 | 977 |
| D3 | Dolomite | 3 | Perumahan | Valid | 56 | 779 |
| D4 | Dolomite | 4 | Perumahan | Valid | 33 | 319 |
| D5 | Dolomite | 5 | Perumahan | Valid | 25 | 457 |
| d5 | Dolomite | 5 | Perumahan | Format Tidak Valid | 1 | 1 |
| D6 | Dolomite | 6 | Perumahan | Valid | 35 | 601 |
| D7 | Ruko Dolomite | 7 | Ruko | Valid | 7 | 31 |
| D8 | Ruko Dolomite | 8 | Ruko | Valid | 11 | 144 |
| DD1 | Diamond | 1 | Perumahan | Valid | 17 | 1.502 |
| DD2 | Diamond | 2 | Perumahan | Valid | 15 | 941 |
| DD3 | Diamond | 3 | Perumahan | Valid | 9 | 618 |
| DR3 | - | - | - | Tidak Teridentifikasi | 1 | 2 |
| E1 | Emerald 48 | 1 | Perumahan | Valid | 55 | 265 |
| E2 | Emerald 48 | 2 | Perumahan | Valid | 64 | 349 |
| E3 | Emerald 48 | 3 | Perumahan | Valid | 68 | 279 |
| E4 | Emerald 48 | 4 | Perumahan | Valid | 41 | 157 |
| E5 | Emerald 48 | 5 | Perumahan | Valid | 31 | 107 |
| E6 | - | - | - | Tidak Teridentifikasi (Ambigu) | 35 | 85 |
| E7 | Ruko Emerald | 7 | Ruko | Valid | 36 | 809 |
| G1 | Garnet | 1 | Perumahan | Valid | 79 | 1.060 |
| G2 | Garnet | 2 | Perumahan | Valid | 113 | 1.247 |
| J1 | Jade | 1 | Perumahan | Valid | 14 | 580 |
| J2 | Jade | 2 | Perumahan | Valid | 15 | 615 |
| J3 | Jade | 3 | Perumahan | Valid | 19 | 579 |
| J5 | Jade | 5 | Perumahan | Valid | 22 | 597 |
| j5 | Jade | 5 | Perumahan | Format Tidak Valid | 1 | 1 |
| J6 | Jade | 6 | Perumahan | Valid | 24 | 818 |
| J7 | Jade | 7 | Perumahan | Valid | 22 | 713 |
| J8 | Jade | 8 | Perumahan | Valid | 13 | 535 |
| J9 | Jade | 9 | Perumahan | Valid | 27 | 1.051 |
| J10 | Jade | 10 | Perumahan | Valid | 11 | 353 |
| JA | Ruko Jade Arcade | - | Ruko | Format Tidak Valid | 27 | 1.683 |
| ja | Ruko Jade Arcade | - | Ruko | Format Tidak Valid | 3 | 6 |
| Unit | - | - | - | Tidak Teridentifikasi | 0 | 9 |

## 5. Cara membaca kolom Unit

- Unit dihitung dari nilai `no unit` yang berbeda pada tiap kode, tanpa membedakan huruf besar/kecil dan nol di depan.
- **Jangan menjumlahkan kolom Unit.** Satu unit bisa muncul di beberapa varian (`AX5` dan `ax5`), sehingga jumlah kolomnya 1.674, bukan 1.638 unit unik.
- Nilai 0 berarti `no unit` kosong atau bukan nomor unit, seperti `Stock`, `Cancel`, dan `??`.
- Nomor gabungan dihitung satu unit: `8&9` di `AX2` dan `6,7,8,9` di `DR3`. Jumlah unit fisiknya bisa lebih banyak.

## 6. Perbedaan dengan folder per blok

Jumlah transaksi sama dengan [final_revisi_1_per_blok/](final_revisi_1_per_blok/) setelah varian huruf digabung. Jumlah unit berbeda di 7 file:

| File | Unit di file | Unit di sini | Penyebab |
|---|---:|---:|---|
| `D5` | 26 | 25 | `28A` dan `28a` adalah satu unit |
| `E1` | 56 | 55 | Satu baris tanpa nomor unit ikut terhitung |
| `??` | 1 | 0 | Tidak ada nomor unit yang sah |
| `CAFE` | 1 | 0 | Tidak ada nomor unit yang sah |
| `CANCEL` | 2 | 0 | Tidak ada nomor unit yang sah |
| `TANPA-BLOK` | 1 | 0 | Tidak ada nomor unit yang sah |
| `UNIT` | 1 | 0 | Tidak ada nomor unit yang sah |
