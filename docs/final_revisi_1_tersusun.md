# Rekap Pembayaran IPL — Grand Duta Residence

> Versi yang disusun ulang dari `final_revisi_1.md` (file asli tidak diubah). Dibuat otomatis oleh script; angka dihitung dari 38.075 baris data.

## 1. Isi file ini

File asli adalah satu tabel besar (Sheet1) berisi **catatan pembayaran iuran (IPL) warga**: siapa yang bayar, unit mana, berapa, kapan, lewat apa, dan siapa petugas yang menerima.

Dokumen ini berisi **ringkasan & rekap**. Data detail per transaksi dipisah per blok di folder [final_revisi_1_per_blok/](final_revisi_1_per_blok/) (diurutkan per unit, lalu tanggal bayar).

## 2. Arti kolom

| Kolom | Arti |
|---|---|
| Nama Penghuni | Nama pembayar/penghuni |
| block, no unit | Lokasi rumah (mis. blok AX5 no 28) |
| NML DBL | Nominal ganda/pembanding (hampir selalu kosong) |
| NOMINAL | Jumlah yang dibayar (Rp) |
| TGL Pembayaran | Tanggal bayar |
| Bulan Tagihan | Periode yang dibayar (mis. "Okt 26", "Agt-Okt 26") |
| NO. KWTANSI 1 / 2 | Nomor kuitansi (1 = nomor urut buku, 2 = nomor format NN./KW./GD.) |
| Tunai/TF/VA | Cara bayar |
| Proses | Jalur pembayaran: Loket, Door To Door, Online |
| PIC | Petugas yang menerima |
| KET (1), KET (2) | Keterangan (status huni, catatan lain) |

## 3. Ringkasan

- Total baris transaksi: **38.075**
- Jumlah blok: **64**
- Total nominal (yang bisa dibaca): **Rp 14.200.044.952**
- Rentang tanggal bayar: **2012-04-30** s/d **2026-12-22**

## 4. Rekap per tahun (berdasarkan tanggal bayar)

| Tahun | Jumlah transaksi | Total nominal (Rp) |
|---|---|---|
| 2012 | 525 | 83.630.500 |
| 2013 | 1323 | 204.609.250 |
| 2014 | 2046 | 489.168.960 |
| 2015 | 4291 | 1.017.492.430 |
| 2016 | 2656 | 842.032.570 |
| 2017 | 2995 | 1.049.888.250 |
| 2018 | 2601 | 917.938.125 |
| 2019 | 1703 | 771.065.753 |
| 2020 | 1628 | 781.708.753 |
| 2021 | 1822 | 907.675.018 |
| 2022 | 2319 | 1.033.475.000 |
| 2023 | 2885 | 1.291.724.862 |
| 2024 | 2974 | 1.383.312.500 |
| 2025 | 3189 | 1.498.120.000 |
| 2026 | 2550 | 1.363.585.131 |

## 5. Rekap per cara bayar

| Cara bayar | Jumlah transaksi | Total nominal (Rp) |
|---|---|---|
| Transfer | 11838 | 5.998.371.644 |
| _(kosong)_ | 11518 | 2.474.158.290 |
| Tunai | 10775 | 3.698.608.531 |
| VA | 1989 | 740.170.000 |
| Debit | 1936 | 1.280.483.487 |
| Lainnya | 19 | 8.253.000 |

## 6. Rekap per jalur proses

| Proses | Jumlah transaksi | Total nominal (Rp) |
|---|---|---|
| Loket | 21714 | 9.927.187.471 |
| _(kosong)_ | 11806 | 2.656.389.100 |
| Online | 2342 | 1.210.085.131 |
| Door To Door | 2104 | 369.122.750 |
| Tunai | 13 | 5.175.000 |
| transfer | 10 | 3.338.000 |
| dwi | 10 | 3.275.000 |
| loker | 10 | 3.175.000 |
| onlne | 10 | 3.675.000 |
| cancel | 9 | 0 |
| debet | 6 | 2.697.500 |
| safari | 6 | 1.500.000 |
| rini | 5 | 1.750.000 |
| loke | 5 | 2.650.000 |
| ofline | 5 | 1.600.000 |
| budi | 4 | 1.575.000 |
| lokey | 3 | 725.000 |
| void | 1 | 225.000 |
| trf | 1 | 250.000 |
| - | 1 | 0 |
| lokat | 1 | 225.000 |
| putri | 1 | 2.250.000 |
| lokrt | 1 | 200.000 |
| lokte | 1 | 450.000 |
| lokeet | 1 | 675.000 |
| loketr | 1 | 675.000 |
| lokety | 1 | 225.000 |
| lokett | 1 | 400.000 |
| irda | 1 | 250.000 |
| offline | 1 | 300.000 |

## 7. Rekap per petugas (PIC)

| PIC | Jumlah transaksi | Total nominal (Rp) |
|---|---|---|
| Riska | 9838 | 4.572.467.362 |
| _(kosong)_ | 8186 | 1.756.461.410 |
| Ratu | 6955 | 3.308.021.131 |
| Ayu | 1863 | 655.160.090 |
| Jangkur | 1639 | 295.010.000 |
| Sulis | 1354 | 756.815.131 |
| Juadih | 1204 | 175.959.820 |
| Metta | 687 | 362.029.500 |
| Rini | 599 | 195.875.260 |
| Rizky | 574 | 165.070.930 |
| Budi | 530 | 145.245.000 |
| Sobari | 498 | 132.950.000 |
| Hilya | 381 | 169.587.500 |
| ayu | 361 | 113.206.500 |
| Markus | 351 | 73.560.000 |
| Dwi | 288 | 165.370.800 |
| Putri | 230 | 101.747.500 |
| Arianto | 188 | 105.610.000 |
| Noviya | 145 | 94.706.000 |
| Ari | 135 | 68.512.500 |
| Safari | 115 | 34.700.000 |
| Dewi | 110 | 54.862.500 |
| Jangkur-Budi | 100 | 10.307.500 |
| Vina | 94 | 40.325.000 |
| Pa Jek | 89 | 25.600.003 |
| RATU | 87 | 48.400.000 |
| Pa Budi | 85 | 44.237.500 |
| Febri | 81 | 39.290.000 |
| Fe | 74 | 24.800.000 |
| Indra | 69 | 37.250.000 |
| jangkur | 60 | 12.345.000 |
| rini | 54 | 21.223.500 |
| Hilya/ Ratu | 51 | 23.225.000 |
| Vina/ Ratu | 44 | 15.800.000 |
| Memet | 39 | 7.617.500 |
| B Ari | 39 | 13.600.000 |
| Hamdani | 37 | 10.970.000 |
| Jangkur/Budi | 34 | 6.675.000 |
| ratu | 32 | 10.575.000 |
| hilya | 32 | 6.050.000 |
| vina/ratu | 31 | 13.737.500 |
| Fitri | 29 | 10.525.000 |
| P jek | 29 | 7.750.000 |
| Siti | 28 | 11.750.000 |
| RINI | 27 | 5.677.500 |
| pa Jek | 23 | 7.000.015 |
| vina | 23 | 13.175.000 |
| memet | 22 | 3.857.500 |
| Pa jek | 21 | 6.250.000 |
| Rsika | 21 | 7.800.000 |
| Rizal | 20 | 7.012.500 |
| B ari | 20 | 9.650.000 |
| P Jek | 18 | 4.500.000 |
| PA JEK | 18 | 4.500.000 |
| Rischa | 18 | 8.300.000 |
| Popon | 17 | 4.250.000 |
| Icha | 17 | 9.917.500 |
| Ickna | 16 | 13.600.000 |
| Vania | 14 | 6.050.000 |
| Wibowo | 13 | 1.800.000 |
| Pak Budi | 13 | 4.875.000 |
| Hilya/Ratu | 13 | 4.200.000 |
| ninda | 12 | 2.275.000 |
| Ade Surya | 12 | 3.000.000 |
| syafari | 11 | 2.750.000 |
| Lika | 9 | 6.000.000 |
| Pbudi | 9 | 3.325.000 |
| Vina/Ratu | 9 | 9.975.000 |
| pa Budi | 8 | 3.250.000 |
| ATS | 8 | 2.000.000 |
| Mba Rini | 7 | 3.875.000 |
| mm | 6 | 3.450.000 |
| febri | 6 | 2.375.000 |
| Pa Ickna | 6 | 2.075.000 |
| Pa Ikna | 6 | 3.025.000 |
| VANIA | 6 | 1.600.000 |
| budi | 5 | 650.000 |
| Ninda | 5 | 750.000 |
| Pahrudin | 5 | 1.250.000 |
| putri | 5 | 1.250.000 |
| FE | 5 | 2.200.000 |
| PA BUDI | 5 | 1.200.000 |
| Pa bUdi | 5 | 1.800.000 |
| pa jek | 5 | 1.250.000 |
| Fadli Security | 4 | 1.260.000 |
| Rizki | 4 | 4.775.000 |
| Mas Budi | 4 | 2.475.000 |
| RA | 4 | 4.425.000 |
| Rtau | 4 | 1.725.000 |
| P. Budi | 4 | 2.850.000 |
| pbudi | 4 | 2.875.000 |
| Ka Ickna | 4 | 875.000 |
| Security | 3 | 600.000 |
| TRF | 3 | 3.200.000 |
| Nursan | 3 | 750.000 |
| Sania | 3 | 950.000 |
| Ali Budiana | 3 | 675.000 |
| cancel | 3 | 0 |
| ICHA | 3 | 2.325.000 |
| ANGGI | 3 | 1.375.000 |
| mfebri | 3 | 1.625.000 |
| Pa budi | 3 | 1.650.000 |
| Bang Ari | 3 | 1.400.000 |
| HILYA | 3 | 1.050.000 |
| Purti | 2 | 550.000 |
| Anggi | 2 | 5.250.000 |
| Firman | 2 | 600.000 |
| Reli | 2 | 1.275.000 |
| icha | 2 | 1.875.000 |
| m. febi | 2 | 1.050.000 |
| M. Febi | 2 | 750.000 |
| FEBRI | 2 | 295.000 |
| Mfebi | 2 | 525.000 |
| Dewii | 2 | 750.000 |
| DEWI | 2 | 1.150.000 |
| Dedi Security | 1 | 600.000 |
| Trf | 1 | 180.000 |
| Rustam Security | 1 | 1.375.000 |
| transfer | 1 | 675.000 |
| Juadih/Rizky | 1 | 600.000 |
| Iman | 1 | 250.000 |
| Metts | 1 | 250.000 |
| Apri | 1 | 225.000 |
| Loket | 1 | 250.000 |
| metta | 1 | 750.000 |
| - | 1 | 0 |
| Matta | 1 | 112.500 |
| Bu Rini | 1 | 200.000 |
| Piutri | 1 | 250.000 |
| Outri | 1 | 200.000 |
| sobari | 1 | 300.000 |
| Cancel | 1 | 0 |
| Pa BuDI | 1 | 750.000 |
| relly | 1 | 225.000 |
| sOBARI | 1 | 250.000 |
| p. budi | 1 | 300.000 |
| P.budi | 1 | 450.000 |
| RAtu | 1 | 300.000 |
| rATU | 1 | 600.000 |
| mfebi | 1 | 250.000 |
| PUTRI | 1 | 3.900.000 |
| Rtu | 1 | 450.000 |
| Ikna | 1 | 900.000 |
| PA Jek | 1 | 750.000 |
| dEWI | 1 | 225.000 |
| pa ickna | 1 | 200.000 |
| Pa jEk | 1 | 200.000 |
| P budi | 1 | 300.000 |
| P Budi | 1 | 600.000 |
| Pa Iknaa | 1 | 350.000 |
| ick | 1 | 1.250.000 |
| B ARI | 1 | 225.000 |
| PA budi | 1 | 400.000 |
| PAJEK | 1 | 250.000 |
| PA JK | 1 | 250.000 |
| PA JEK] | 1 | 250.000 |
| Pa JEK | 1 | 600.000 |
| Hilya] | 1 | 250.000 |
| Danisa | 1 | 900.000 |
| riska | 1 | 500.000 |
| ARI | 1 | 300.000 |
| Ratu Tidak Input | 1 | 0 |

## 8. Rekap per blok

| Blok | Jumlah unit | Jumlah transaksi | Total nominal (Rp) | Detail |
|---|---|---|---|---|
| ?? | 1 | 3 | 850.000 | [??.md](final_revisi_1_per_blok/??.md) |
| AL2 | 14 | 500 | 272.700.005 | [AL2.md](final_revisi_1_per_blok/AL2.md) |
| AM1 | 14 | 1059 | 417.130.000 | [AM1.md](final_revisi_1_per_blok/AM1.md) |
| AM2 | 16 | 1124 | 426.817.503 | [AM2.md](final_revisi_1_per_blok/AM2.md) |
| AR1 | 8 | 45 | 24.800.000 | [AR1.md](final_revisi_1_per_blok/AR1.md) |
| AR2 | 9 | 49 | 22.014.000 | [AR2.md](final_revisi_1_per_blok/AR2.md) |
| AR3 | 9 | 71 | 24.990.000 | [AR3.md](final_revisi_1_per_blok/AR3.md) |
| AR5 | 17 | 105 | 50.262.500 | [AR5.md](final_revisi_1_per_blok/AR5.md) |
| AX2 | 2 | 52 | 38.030.000 | [AX2.md](final_revisi_1_per_blok/AX2.md) |
| AX3 | 41 | 1679 | 647.190.003 | [AX3.md](final_revisi_1_per_blok/AX3.md) |
| AX5 | 67 | 2338 | 960.150.000 | [AX5.md](final_revisi_1_per_blok/AX5.md) |
| AX6 | 32 | 421 | 228.825.000 | [AX6.md](final_revisi_1_per_blok/AX6.md) |
| AX7 | 30 | 780 | 452.100.000 | [AX7.md](final_revisi_1_per_blok/AX7.md) |
| AX8 | 41 | 1608 | 952.500.000 | [AX8.md](final_revisi_1_per_blok/AX8.md) |
| AX9 | 2 | 164 | 68.350.000 | [AX9.md](final_revisi_1_per_blok/AX9.md) |
| B10 | 52 | 880 | 431.430.000 | [B10.md](final_revisi_1_per_blok/B10.md) |
| B7 | 8 | 62 | 31.500.000 | [B7.md](final_revisi_1_per_blok/B7.md) |
| B8 | 32 | 449 | 239.450.000 | [B8.md](final_revisi_1_per_blok/B8.md) |
| B9 | 45 | 694 | 356.160.000 | [B9.md](final_revisi_1_per_blok/B9.md) |
| C1 | 17 | 913 | 362.486.125 | [C1.md](final_revisi_1_per_blok/C1.md) |
| C10 | 6 | 301 | 156.656.862 | [C10.md](final_revisi_1_per_blok/C10.md) |
| C11 | 11 | 730 | 315.775.750 | [C11.md](final_revisi_1_per_blok/C11.md) |
| C2 | 10 | 356 | 222.784.350 | [C2.md](final_revisi_1_per_blok/C2.md) |
| C3 | 10 | 375 | 190.929.500 | [C3.md](final_revisi_1_per_blok/C3.md) |
| C5 | 9 | 450 | 231.173.240 | [C5.md](final_revisi_1_per_blok/C5.md) |
| C6 | 18 | 1004 | 455.175.290 | [C6.md](final_revisi_1_per_blok/C6.md) |
| C7 | 15 | 634 | 364.752.180 | [C7.md](final_revisi_1_per_blok/C7.md) |
| C8 | 11 | 687 | 283.124.810 | [C8.md](final_revisi_1_per_blok/C8.md) |
| C9 | 11 | 658 | 283.818.100 | [C9.md](final_revisi_1_per_blok/C9.md) |
| CAFE | 1 | 4 | 2.500.000 | [CAFE.md](final_revisi_1_per_blok/CAFE.md) |
| CANCEL | 2 | 11 | 0 | [CANCEL.md](final_revisi_1_per_blok/CANCEL.md) |
| D1 | 84 | 1365 | 223.116.250 | [D1.md](final_revisi_1_per_blok/D1.md) |
| D2 | 72 | 977 | 158.416.250 | [D2.md](final_revisi_1_per_blok/D2.md) |
| D3 | 56 | 779 | 141.367.500 | [D3.md](final_revisi_1_per_blok/D3.md) |
| D4 | 33 | 319 | 68.396.250 | [D4.md](final_revisi_1_per_blok/D4.md) |
| D5 | 26 | 458 | 67.840.500 | [D5.md](final_revisi_1_per_blok/D5.md) |
| D6 | 35 | 601 | 86.763.500 | [D6.md](final_revisi_1_per_blok/D6.md) |
| D7 | 7 | 31 | 23.100.000 | [D7.md](final_revisi_1_per_blok/D7.md) |
| D8 | 11 | 144 | 104.300.000 | [D8.md](final_revisi_1_per_blok/D8.md) |
| DD1 | 17 | 1502 | 501.482.500 | [DD1.md](final_revisi_1_per_blok/DD1.md) |
| DD2 | 15 | 941 | 467.752.500 | [DD2.md](final_revisi_1_per_blok/DD2.md) |
| DD3 | 9 | 618 | 281.022.500 | [DD3.md](final_revisi_1_per_blok/DD3.md) |
| DR3 | 1 | 2 | 18.000.000 | [DR3.md](final_revisi_1_per_blok/DR3.md) |
| E1 | 56 | 265 | 61.293.750 | [E1.md](final_revisi_1_per_blok/E1.md) |
| E2 | 64 | 349 | 70.963.600 | [E2.md](final_revisi_1_per_blok/E2.md) |
| E3 | 68 | 279 | 68.227.500 | [E3.md](final_revisi_1_per_blok/E3.md) |
| E4 | 41 | 157 | 32.487.500 | [E4.md](final_revisi_1_per_blok/E4.md) |
| E5 | 31 | 107 | 30.687.500 | [E5.md](final_revisi_1_per_blok/E5.md) |
| E6 | 35 | 85 | 19.527.500 | [E6.md](final_revisi_1_per_blok/E6.md) |
| E7 | 36 | 809 | 558.380.000 | [E7.md](final_revisi_1_per_blok/E7.md) |
| G1 | 79 | 1060 | 213.983.000 | [G1.md](final_revisi_1_per_blok/G1.md) |
| G2 | 113 | 1247 | 252.069.500 | [G2.md](final_revisi_1_per_blok/G2.md) |
| J1 | 14 | 580 | 132.905.000 | [J1.md](final_revisi_1_per_blok/J1.md) |
| J10 | 11 | 353 | 84.586.000 | [J10.md](final_revisi_1_per_blok/J10.md) |
| J2 | 15 | 615 | 138.582.100 | [J2.md](final_revisi_1_per_blok/J2.md) |
| J3 | 19 | 579 | 155.110.000 | [J3.md](final_revisi_1_per_blok/J3.md) |
| J5 | 22 | 598 | 166.877.200 | [J5.md](final_revisi_1_per_blok/J5.md) |
| J6 | 24 | 818 | 204.962.980 | [J6.md](final_revisi_1_per_blok/J6.md) |
| J7 | 22 | 713 | 185.688.960 | [J7.md](final_revisi_1_per_blok/J7.md) |
| J8 | 13 | 535 | 117.771.000 | [J8.md](final_revisi_1_per_blok/J8.md) |
| J9 | 27 | 1051 | 252.930.000 | [J9.md](final_revisi_1_per_blok/J9.md) |
| JA | 27 | 1689 | 653.326.263 | [JA.md](final_revisi_1_per_blok/JA.md) |
| TANPA-BLOK | 1 | 234 | 2.525.000 | [TANPA-BLOK.md](final_revisi_1_per_blok/TANPA-BLOK.md) |
| UNIT | 1 | 9 | 141.177.631 | [UNIT.md](final_revisi_1_per_blok/UNIT.md) |

## 9. Catatan kualitas data

Data asli diisi manual sehingga ada hal yang perlu diketahui:

- **1.625** baris tanpa tanggal bayar.
- **943** baris dengan format tanggal yang tidak bisa dibaca otomatis (ditampilkan apa adanya di file per blok, tidak masuk rekap per tahun).
- **262** baris dengan nominal tanpa pemisah (mis. `225`) atau kosong; tidak dimasukkan ke total.
- **213** baris berstatus *Cancel*.
- Penulisan dinormalkan: cara bayar (Transfer/Tranfer/transfer → Transfer; Debet → Debit), proses (loket/LOKET → Loket; DTD → Door To Door), status huni (huni/sudah huni → Sudah huni), dan tanggal ke format YYYY-MM-DD.
- Kolom *Bulan Tagihan* formatnya sangat beragam sehingga dibiarkan seperti aslinya.
