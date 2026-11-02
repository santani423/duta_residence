# Pembayaran IPL per Cluster — Grand Duta Residence

Seluruh 38.075 baris [final_revisi_1.md](../final_revisi_1.md) disusun per cluster, lalu per blok, unit, dan tanggal bayar. File sumber tidak diubah dan setiap baris disalin apa adanya dengan 14 kolom aslinya.

Pengelompokan mengikuti [Klasifikasi Kode Blok](../final_revisi_1_klasifikasi_blok.md). Arti kolom ada di [Rekap Pembayaran IPL](../final_revisi_1_tersusun.md#2-arti-kolom).

## Rekap per cluster

| Cluster | Kode | Jenis | Blok | Unit | Transaksi | Total nominal (Rp) | Di luar total (baris) |
|---|---|---|---:|---:|---:|---:|---:|
| [Alexandrite](Alexandrite.md) | AX | Perumahan | 7 | 215 | 7.042 | 3.347.145.003 | 107 |
| Ruko Alexandrite | AL- | Ruko | 0 | 0 | 0 | 0 | 0 |
| [Amber](Amber.md) | AR | Perumahan | 4 | 43 | 270 | 122.066.500 | 0 |
| [Ruko Amber](Ruko-Amber.md) | AM | Ruko | 2 | 30 | 2.183 | 843.947.503 | 17 |
| [Beryl](Beryl.md) | B | Perumahan | 4 | 137 | 2.085 | 1.058.540.000 | 17 |
| [Chrysocolla](Chrysocolla.md) | C | Perumahan | 10 | 118 | 6.108 | 2.866.676.207 | 53 |
| [Diamond](Diamond.md) | DD | Perumahan | 3 | 41 | 3.061 | 1.250.257.500 | 24 |
| [Dolomite](Dolomite.md) | D (1–6) | Perumahan | 6 | 305 | 4.499 | 745.900.250 | 1 |
| [Ruko Dolomite](Ruko-Dolomite.md) | D (7–8) | Ruko | 2 | 18 | 175 | 127.400.000 | 0 |
| [Emerald 48](Emerald-48.md) | E (1–5) | Perumahan | 5 | 259 | 1.157 | 263.659.850 | 0 |
| [Ruko Emerald](Ruko-Emerald.md) | E (7) | Ruko | 1 | 36 | 809 | 558.380.000 | 8 |
| [Garnet](Garnet.md) | G | Perumahan | 2 | 192 | 2.307 | 466.052.500 | 0 |
| [Jade](Jade.md) | J | Perumahan | 9 | 167 | 5.842 | 1.439.413.240 | 0 |
| [Ruko Jade Arcade](Ruko-Jade-Arcade.md) | JA | Ruko | - | 27 | 1.689 | 653.326.263 | 17 |
| [Belum Terklasifikasi](Belum-Terklasifikasi.md) | - | - | - | 50 | 848 | 457.280.136 | 1 |
| **Total** | | | | **1.638** | **38.075** | **14.200.044.952** | **245** |

## Perlu diputuskan

- **Ruko Alexandrite masih kosong.** Aturan hanya mengenal kode `AL-`, sedangkan buku IPL memakai `AL2`. Transaksi `AL2` dan `E6` ada di [Belum Terklasifikasi](Belum-Terklasifikasi.md) sampai ada keputusan.
- **Total nominal belum mencakup 245 baris.** Cara hitungnya sama dengan rekap lama, jadi angkanya tetap sama dengan [Rekap Pembayaran IPL](../final_revisi_1_tersusun.md#8-rekap-per-blok). Barisnya tetap ada di data:
  - 241 baris tertulis tanpa pemisah, misalnya `225`. Jika dikali 1.000, 237 di antaranya sama persis dengan pembayaran lain di unit yang sama, jadi hampir pasti maksudnya ribuan. Jika benar, total bertambah Rp 78.800.000.
  - 4 baris berawalan `Rp`, misalnya `Rp500.000,00`, senilai Rp 1.900.000.

## Cara membaca

- **Varian huruf digabung.** 39 baris yang kodenya memakai huruf kecil, seperti `Ax5`, masuk ke blok `AX5`. Kode aslinya tetap terlihat di kolom `block`.
- **`JA` tidak punya nomor blok,** jadi seluruh Ruko Jade Arcade berada dalam satu kelompok.
- **Isian bukan angka** di kolom nominal, seperti `Cancel`, `FREE`, `salah`, dan `-`, dihitung 0.
- **Unit** dihitung dari nomor yang berbeda per blok, tanpa membedakan huruf besar/kecil dan nol di depan. Baris tanpa nomor unit yang sah tetap dicantumkan, tetapi tidak dihitung sebagai unit.
- **Urutan tanggal** membaca format seperti `29 Des 17` dan `26/01/2016` tanpa mengubah isinya. Baris tanpa tanggal yang bisa dibaca ada di akhir tiap unit.
