# Sampel Pembayaran IPL

Salinan buku IPL manual (worksheet `ALL` pada "IPKL Manual 2017 UPTODATE"), dikelompokkan per bulan bayar dan diurutkan menurut tanggal bayar.

- Rentang tanggal bayar: 2023-12-15 s.d. 2026-10-07
- Jumlah transaksi: 1.747
- Total nominal: Rp 481.075.000
- File ini adalah sumber data pembayaran IPL untuk seeder (parser: `database/seeders/IplData.php`). Pembayaran yang tidak tercatat di sini tidak diimpor. Baris yang blok/nomornya tidak ada di `database/data/pelanggan.csv` dilewati dan dilaporkan saat seeding.

## Keterangan kolom

| Kolom        | Isi                                        |
| ------------ | ------------------------------------------ |
| Nama         | Nama pembayar                              |
| Blok / No.   | Blok dan nomor rumah (`8&9` = dua kavling) |
| Nominal (Rp) | Jumlah yang dibayar                        |
| Tgl Bayar    | Tanggal pembayaran (YYYY-MM-DD)            |
| Periode      | Bulan IPL yang dibayar (YYYY-MM)           |
| No. Kwitansi | Nomor kwitansi manual                      |
| Cara Bayar   | Transfer, VA, atau Tunai                   |
| PIC          | Loket atau Online                          |
| Petugas      | Petugas yang mencatat                      |
| Catatan      | Catatan tambahan (hanya muncul jika ada)   |

Catatan pengolahan: kolom kosong `NML DBL` dan `Keterangan` dihapus. Baris Liyanawati AX7/16 (kwitansi 002810) tertempel 1.803 kali di salinan Excel; di sini ditulis sekali. Isi sel lain tidak diubah, termasuk salah ketik seperti "Ofline" dan "Rsika".

## Ringkasan per bulan bayar

| Bulan bayar    | Transaksi |      Total (Rp) |
| -------------- | --------: | --------------: |
| Desember 2023  |         1 |         350.000 |
| Februari 2025  |        36 |       9.775.000 |
| Maret 2025     |        89 |      23.850.000 |
| April 2025     |        33 |       8.675.000 |
| Mei 2025       |       116 |      31.500.000 |
| Juni 2025      |       181 |      48.925.000 |
| Juli 2025      |       188 |      51.375.000 |
| Agustus 2025   |        35 |       9.575.000 |
| September 2025 |       166 |      45.400.000 |
| Oktober 2025   |        35 |      10.050.000 |
| November 2025  |       164 |      45.675.000 |
| Desember 2025  |        38 |      10.450.000 |
| Januari 2026   |       183 |      50.950.000 |
| Februari 2026  |       173 |      48.375.000 |
| Maret 2026     |        25 |       7.250.000 |
| April 2026     |        16 |       4.175.000 |
| Mei 2026       |        20 |       5.625.000 |
| Juni 2026      |         9 |       2.350.000 |
| Juli 2026      |         5 |       1.250.000 |
| Agustus 2026   |        58 |      16.500.000 |
| September 2026 |       161 |      45.050.000 |
| Oktober 2026   |        15 |       3.950.000 |
| **Total**      | **1.747** | **481.075.000** |

## Desember 2023

| Nama       | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC   | Petugas |
| ---------- | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ----- | ------- |
| Liyanawati | AX7  | 16  |      350.000 | 2023-12-15 | 2024-01 | 002810       | Transfer   | Loket | Riska   |

## Februari 2025

| Nama                 | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC   | Petugas |
| -------------------- | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ----- | ------- |
| Mohamad Amar         | AL2  | 10  |      300.000 | 2025-02-24 | 2025-02 | 000429       | Transfer   | Loket | Riska   |
| Riyandi Susanto      | C11  | 28  |      225.000 | 2025-02-24 | 2025-02 | 000430       | Transfer   | Loket | Riska   |
| Siaw Ling            | AX3  | 89  |      200.000 | 2025-02-24 | 2025-02 | 000431       | Transfer   | Loket | Riska   |
| Ikhwan Rosyiidian    | AX8  | 52  |      300.000 | 2025-02-24 | 2025-02 | 000433       | Transfer   | Loket | Riska   |
| Herlika              | AX5  | 35  |      250.000 | 2025-02-24 | 2025-02 | 000435       | Transfer   | Loket | Riska   |
| Siti Fatonah         | AX5  | 32  |      200.000 | 2025-02-25 | 2025-02 | 000439       | Tunai      | Loket | Riska   |
| Lucky Suryana        | B9   | 17  |      300.000 | 2025-02-25 | 2024-12 | 000440       | Tunai      | Loket | Riska   |
| Konnie Gunawan       | B9   | 58  |      300.000 | 2025-02-25 | 2025-02 | 000441       | Transfer   | Loket | Riska   |
| Yuliana              | C6   | 31  |      225.000 | 2025-02-25 | 2025-02 | 000443       | Transfer   | Loket | Riska   |
| Nani Junaeni         | AX8  | 19  |      300.000 | 2025-02-25 | 2025-02 | 000446       | Transfer   | Loket | Riska   |
| Andrew               | C8   | 3   |      225.000 | 2025-02-25 | 2025-01 | 000447       | Transfer   | Loket | Riska   |
| Handrian Wijaya      | DD1  | 5   |      250.000 | 2025-02-25 | 2025-02 | 000448       | Transfer   | Loket | Riska   |
| Fransisca            | AX7  | 17  |      350.000 | 2025-02-25 | 2025-02 | 000449       | Transfer   | Loket | Riska   |
| Henry Tulus          | DD2  | 5   |      250.000 | 2025-02-25 | 2025-02 | 000450       | Transfer   | Loket | Riska   |
| Yelsikasari          | B9   | 60  |      300.000 | 2025-02-25 | 2025-02 | 000451       | Transfer   | Loket | Riska   |
| Lina Risnawati       | E7   | 26  |      300.000 | 2025-02-25 | 2025-02 | 000454       | Transfer   | Loket | Riska   |
| Dessy                | AX7  | 55  |      350.000 | 2025-02-26 | 2025-02 | 000461       | Transfer   | Loket | Riska   |
| Ediana Sigalingging  | AM2  | 1   |      300.000 | 2025-02-26 | 2025-02 | 000462       | Tunai      | Loket | Riska   |
| Ediana Sigalingging  | AM2  | 2   |      300.000 | 2025-02-26 | 2025-02 | 000463       | Tunai      | Loket | Riska   |
| Arlia Anwar          | B10  | 78  |      300.000 | 2025-02-26 | 2025-02 | 000464       | Transfer   | Loket | Riska   |
| Sahrial Lubis        | C2   | 27  |      225.000 | 2025-02-27 | 2025-02 | 000466       | Tunai      | Loket | Riska   |
| Carolina             | C1   | 10  |      225.000 | 2025-02-27 | 2024-12 | 000468       | Transfer   | Loket | Riska   |
| Mohamad Yusuf        | B8   | 81  |      300.000 | 2025-02-27 | 2025-02 | 000469       | Transfer   | Loket | Riska   |
| Nesa Apriari         | B8   | 67  |      300.000 | 2025-02-27 | 2025-01 | 000470       | Transfer   | Loket | Riska   |
| Jhoni Candra         | C1   | 6   |      225.000 | 2025-02-27 | 2025-02 | 000471       | Transfer   | Loket | Riska   |
| Sukardi              | AX5  | 21  |      250.000 | 2025-02-27 | 2025-01 | 000472       | Transfer   | Loket | Riska   |
| Riko                 | AX3  | 11  |      200.000 | 2025-02-28 | 2025-01 | 000482       | Transfer   | Loket | Riska   |
| Lindawati            | B10  | 39  |      300.000 | 2025-02-28 | 2025-02 | 000486       | Transfer   | Loket | Riska   |
| Dennie               | B9   | 29  |      300.000 | 2025-02-28 | 2025-01 | 000488       | Transfer   | Loket | Riska   |
| Rangga Afyan Dwiokta | AX6  | 62  |      250.000 | 2025-02-28 | 2025-02 | 000489       | Transfer   | Loket | Riska   |
| Stephanie Setiawan   | DD1  | 6   |      250.000 | 2025-02-28 | 2025-02 | 000490       | Transfer   | Loket | Riska   |
| Benny Supardjo       | C6   | 29  |      225.000 | 2025-02-28 | 2025-02 | 000491       | Transfer   | Loket | Riska   |
| Putri Avina          | JA   | 18  |      300.000 | 2025-02-28 | 2025-02 | 000492       | Transfer   | Loket | Riska   |
| Putri Avina          | AX7  | 27  |      350.000 | 2025-02-28 | 2025-02 | 000493       | Transfer   | Loket | Riska   |
| Badai Rosamudra      | AX5  | 56  |      200.000 | 2025-02-28 | 2025-01 | 000494       | Transfer   | Loket | Riska   |
| Noviana              | AX7  | 33  |      350.000 | 2025-02-28 | 2025-02 | 000495       | Transfer   | Loket | Riska   |

## Maret 2025

| Nama                    | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC    | Petugas |
| ----------------------- | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ------ | ------- |
| Vina Karya              | AL2  | 11  |      300.000 | 2025-03-03 | 2025-02 | 000499       | Tunai      | Loket  | Riska   |
| Ernest Kurniawati       | DD2  | 8   |      250.000 | 2025-03-03 | 2025-02 | 000503       | Transfer   | Loket  | Riska   |
| Teddy Suwanda           | AX8  | 11  |      300.000 | 2025-03-03 | 2025-01 | 000505       | Transfer   | Loket  | Riska   |
| Luman                   | AX7  | 37  |      350.000 | 2025-03-03 | 2025-03 | 000506       | Transfer   | Loket  | Riska   |
| Budhi Mulyadi           | DD3  | 3   |      250.000 | 2025-03-03 | 2025-03 | 000507       | Transfer   | Loket  | Riska   |
| Regina Adytia           | AX8  | 3   |      300.000 | 2025-03-03 | 2025-02 | 000510       | Transfer   | Loket  | Riska   |
| Muhammad Fachdilah      | AX6  | 70  |      250.000 | 2025-03-03 | 2025-03 | 000511       | Transfer   | Loket  | Riska   |
| Julio Andrian           | AX3  | 29  |      200.000 | 2025-03-03 | 2025-03 | 000512       | Transfer   | Loket  | Riska   |
| Indomaret Grand Duta 2  | E7   | 27  |      300.000 | 2025-03-03 | 2025-02 | 000513       | Tunai      | Loket  | Riska   |
| Indomaret Grand Duta 2  | E7   | 28  |      300.000 | 2025-03-03 | 2025-02 | 000514       | Tunai      | Loket  | Riska   |
| Joni                    | AX7  | 23  |      350.000 | 2025-03-03 | 2025-01 | 000527       | Transfer   | Loket  | Riska   |
| Supangat                | AX8  | 33  |      300.000 | 2025-03-03 | 2025-02 | 000529       | Transfer   | Loket  | Riska   |
| Stefanus                | C3   | 9   |      225.000 | 2025-03-03 | 2025-02 | 000532       | Transfer   | Loket  | Riska   |
| Yani Lestari            | AX7  | 6   |      350.000 | 2025-03-04 | 2025-02 | 000536       | VA         | Loket  | Riska   |
| Edy Hartono             | JA   | 19  |      300.000 | 2025-03-04 | 2025-02 | 000537       | VA         | Loket  | Riska   |
| Lista Akdriaty          | C3   | 7   |      225.000 | 2025-03-04 | 2025-01 | 000540       | VA         | Loket  | Riska   |
| Dalyano Kayambo         | AX3  | 81  |      200.000 | 2025-03-04 | 2025-01 | 000541       | VA         | Loket  | Riska   |
| Lili                    | AX5  | 36  |      200.000 | 2025-03-04 | 2025-02 | 000544       | VA         | Loket  | Riska   |
| Lady Diana              | C7   | 56  |      225.000 | 2025-03-04 | 2025-01 | 000547       | VA         | Loket  | Riska   |
| Mohammad Syukron        | C6   | 19  |      225.000 | 2025-03-04 | 2025-02 | 000549       | VA         | Loket  | Riska   |
| Ajeng                   | DD1  | 17  |      250.000 | 2025-03-04 | 2025-02 | 000550       | VA         | Loket  | Riska   |
| Eva Darmayanti          | JA   | 27  |      300.000 | 2025-03-04 | 2025-01 | 000551       | VA         | Loket  | Riska   |
| Dion Yonathan           | C6   | 39  |      225.000 | 2025-03-04 | 2025-01 | 000552       | VA         | Loket  | Riska   |
| Theresia Sayuti         | AM2  | 11  |      300.000 | 2025-03-04 | 2025-01 | 000554       | VA         | Loket  | Riska   |
| Ronald                  | DD1  | 3   |      250.000 | 2025-03-04 | 2025-01 | 000556       | VA         | Loket  | Riska   |
| Budiman                 | C6   | 61  |      225.000 | 2025-03-04 | 2024-09 | 000557       | Tunai      | Loket  | Riska   |
| ME Diah Ayu             | B10  | 27  |      300.000 | 2025-03-04 | 2025-02 | 000559       | Transfer   | Loket  | Riska   |
| Rizki Andriani          | B9   | 6   |      300.000 | 2025-03-04 | 2024-10 | 000561       | Transfer   | Loket  | Riska   |
| Theresia Suyati         | AM2  | 11  |      250.000 | 2025-03-04 | 2024-12 | 000568       | Transfer   | Loket  | Riska   |
| Evi Yulianti            | AX3  | 35  |      200.000 | 2025-03-04 | 2025-01 | 000570       | Transfer   | Loket  | Riska   |
| Lilih Yuliani           | AX5  | 36  |      200.000 | 2025-03-04 | 2026-02 | 00579        | Transfer   | Online | Riska   |
| Juan Ignatius           | AX3  | 1   |      200.000 | 2025-03-04 | 2026-02 | 00583        | Transfer   | Online | Riska   |
| Ristianti               | C3   | 17  |      225.000 | 2025-03-05 | 2024-07 | 000578       | VA         | Loket  | Riska   |
| Ronald yahya            | C1   | 2   |      225.000 | 2025-03-05 | 2025-01 | 000583       | VA         | Loket  | Riska   |
| Pramadasyah             | B9   | 32  |      300.000 | 2025-03-05 | 2025-02 | 000585       | Transfer   | Loket  | Riska   |
| Anista Retno            | JA   | 16  |      300.000 | 2025-03-05 | 2025-02 | 000586       | Transfer   | Loket  | Riska   |
| Firdha                  | B10  | 26  |      300.000 | 2025-03-05 | 2025-02 | 000590       | Tunai      | Loket  | Riska   |
| Ghofar Maulana          | E7   | 35  |      300.000 | 2025-03-06 | 2024-10 | 000601       | VA         | Loket  | Riska   |
| Tri Septiyanti          | E7   | 7   |      300.000 | 2025-03-06 | 2025-02 | 000605       | VA         | Loket  | Riska   |
| Data Integritas Inovasi | D8   | 8   |      350.000 | 2025-03-06 | 2024-12 | 000606       | VA         | Loket  | Riska   |
| Rahayu Nawang           | D8   | 9   |      350.000 | 2025-03-06 | 2024-10 | 000607       | VA         | Loket  | Riska   |
| Antonius Yulian         | AX3  | 83  |      200.000 | 2025-03-06 | 2025-01 | 000609       | VA         | Loket  | Riska   |
| Giffari Lestyonanda     | B8   | 27  |      300.000 | 2025-03-06 | 2025-01 | 000610       | VA         | Loket  | Riska   |
| Endah Siwih             | C5   | 10  |      225.000 | 2025-03-06 | 2025-01 | 000612       | VA         | Loket  | Riska   |
| Lisa Loandha            | C11  | 20  |      225.000 | 2025-03-06 | 2025-03 | 000613       | Transfer   | Loket  | Riska   |
| Freddy Pribadi          | AX3  | 75  |      200.000 | 2025-03-07 | 2025-01 | 000624       | VA         | Loket  | Riska   |
| Gwat Lie                | AM1  | 1   |      300.000 | 2025-03-07 | 2025-01 | 000625       | VA         | Loket  | Riska   |
| Idrus                   | E7   | 10  |      300.000 | 2025-03-07 | 2024-12 | 000627       | VA         | Loket  | Riska   |
| Lusiana                 | AX8  | 38  |      300.000 | 2025-03-07 | 2025-01 | 000629       | VA         | Loket  | Riska   |
| Ricke Surya             | AX6  | 76  |      250.000 | 2025-03-07 | 2025-02 | 000634       | VA         | Loket  | Riska   |
| Euis Tuty               | JA   | 21  |      300.000 | 2025-03-07 | 2025-02 | 000642       | Tunai      | Loket  | Riska   |
| Mochamad Sachuri        | E7   | 25  |      300.000 | 2025-03-10 | 2024-12 | 000643       | VA         | Loket  | Riska   |
| Julianto                | AX5  | 77  |      250.000 | 2025-03-10 | 2025-02 | 000646       | VA         | Loket  | Riska   |
| Yenni                   | C5   | 12  |      225.000 | 2025-03-11 | 2025-02 | 000659       | VA         | Loket  | Riska   |
| Novita Anggraeni        | AM2  | 12  |      300.000 | 2025-03-12 | 2025-01 | 000671       | VA         | Loket  | Riska   |
| Novita Anggraeni        | AX5  | 96  |      200.000 | 2025-03-12 | 2025-01 | 000673       | Transfer   | Loket  | Riska   |
| Jaya Iskandar           | C9   | 8   |      225.000 | 2025-03-13 | 2025-02 | 000682       | Tunai      | Loket  | Riska   |
| Herson                  | AX9  | 38  |      300.000 | 2025-03-13 | 2025-02 | 000683       | Transfer   | Loket  | Riska   |
| Herson                  | DD2  | 19  |      250.000 | 2025-03-13 | 2025-02 | 000684       | Transfer   | Loket  | Riska   |
| Herson                  | C6   | 55  |      225.000 | 2025-03-13 | 2025-02 | 000685       | Transfer   | Loket  | Riska   |
| Herson                  | AL2  | 5   |      300.000 | 2025-03-13 | 2025-02 | 000687       | Transfer   | Loket  | Riska   |
| Kajohan                 | AX5  | 80  |      200.000 | 2025-03-14 | 2025-03 | 000695       | VA         | Loket  | Riska   |
| Siti Nurwati            | AX6  | 17  |      350.000 | 2025-03-14 | 2025-03 | 000696       | VA         | Loket  | Riska   |
| Dian Eka Lestari        | AX5  | 19  |      250.000 | 2025-03-14 | 2025-02 | 000698       | Transfer   | Loket  | Riska   |
| Teguh Santoso           | C3   | 3   |      225.000 | 2025-03-17 | 2025-02 | 000703       | VA         | Loket  | Riska   |
| Diah Fajar Sari         | C1   | 5   |      225.000 | 2025-03-17 | 2025-02 | 000706       | VA         | Loket  | Riska   |
| Dedi Darmanto           | AM2  | 8   |      300.000 | 2025-03-18 | 2025-01 | 000719       | VA         | Loket  | Riska   |
| Rizky Arif              | AX8  | 5   |      300.000 | 2025-03-18 | 2025-02 | 000725       | Tunai      | Loket  | Riska   |
| Arjumulia               | B8   | 68  |      300.000 | 2025-03-19 | 2024-10 | 000727       | VA         | Loket  | Riska   |
| Etri Septivia           | B9   | 59  |      300.000 | 2025-03-19 | 2025-01 | 000732       | Transfer   | Loket  | Riska   |
| Linda Karlinda          | JA   | 30  |      300.000 | 2025-03-21 | 2025-03 | 000739       | Transfer   | Loket  | Riska   |
| Siti Fatonah            | AX5  | 32  |      200.000 | 2025-03-21 | 2025-03 | 000740       | Tunai      | Loket  | Riska   |
| Cedy Chandra            | AX6  | 3   |      350.000 | 2025-03-21 | 2025-03 | 000741       | VA         | Loket  | Riska   |
| Elroys Octavianus       | B10  | 63  |      300.000 | 2025-03-21 | 2025-03 | 000742       | VA         | Loket  | Riska   |
| Sabeth                  | AX8  | 30  |      300.000 | 2025-03-21 | 2025-03 | 000743       | VA         | Loket  | Riska   |
| Rice Saxena             | C9   | 5   |      225.000 | 2025-03-21 | 2025-03 | 000744       | VA         | Loket  | Riska   |
| Eriyono Budi            | AX3  | 69  |      200.000 | 2025-03-21 | 2025-03 | 000745       | VA         | Loket  | Riska   |
| Finna Clasera           | B8   | 18  |      300.000 | 2025-03-24 | 2025-02 | 000751       | VA         | Loket  | Riska   |
| Shafa Salsabila         | AM2  | 18  |      300.000 | 2025-03-24 | 2025-02 | 000753       | VA         | Loket  | Riska   |
| Efendi                  | B8   | 75  |      300.000 | 2025-03-24 | 2025-02 | 000755       | VA         | Loket  | Riska   |
| Bram                    | AX7  | 31  |      350.000 | 2025-03-25 | 2025-02 | 000761       | VA         | Loket  | Riska   |
| Kristiandi              | B9   | 56  |      300.000 | 2025-03-25 | 2025-02 | 000762       | VA         | Loket  | Riska   |
| Yohan Hosia             | DD2  | 3   |      250.000 | 2025-03-25 | 2025-02 | 000763       | Transfer   | Loket  | Riska   |
| Jonathan Surya          | B9   | 79  |      300.000 | 2025-03-25 | 2025-02 | 000768       | Transfer   | Loket  | Riska   |
| Yenni                   | C9   | 6   |      225.000 | 2025-03-26 | 2025-02 | 000777       | VA         | Loket  | Arianto |
| Muhammad Nico           | B8   | 30  |      300.000 | 2025-03-26 | 2024-10 | 000779       | VA         | Loket  | Arianto |
| Fransisca Natalia       | C11  | 16  |      225.000 | 2025-03-26 | 2025-02 | 000780       | VA         | Loket  | Arianto |
| Sukardi                 | AX5  | 21  |      250.000 | 2025-03-26 | 2025-02 | 000781       | Transfer   | Loket  | Arianto |
| Jonathan                | AX3  | 5   |      200.000 | 2025-03-26 | 2024-09 | 000782       | Transfer   | Loket  | Arianto |

## April 2025

| Nama                | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC   | Petugas |
| ------------------- | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ----- | ------- |
| Joni                | AX7  | 23  |      350.000 | 2025-04-08 | 2025-02 | 000793       | VA         | Loket | Riska   |
| Lista Akdriaty      | C3   | 7   |      225.000 | 2025-04-08 | 2025-02 | 000801       | VA         | Loket | Riska   |
| Dion Yonathan       | C6   | 39  |      225.000 | 2025-04-08 | 2025-02 | 000825       | VA         | Loket | Riska   |
| Lukmanul hakim      | C8   | 9   |      225.000 | 2025-04-08 | 2024-12 | 000828       | Transfer   | Loket | Riska   |
| Andrew              | C8   | 3   |      225.000 | 2025-04-08 | 2025-02 | 000829       | Transfer   | Loket | Riska   |
| Badai rosamudra     | AX5  | 56  |      200.000 | 2025-04-08 | 2025-02 | 000838       | Transfer   | Loket | Riska   |
| Dennie              | B9   | 29  |      300.000 | 2025-04-08 | 2025-02 | 000875       | Transfer   | Loket | Riska   |
| Lina                | AX5  | 68  |      200.000 | 2025-04-09 | 2025-02 | 000878       | Transfer   | Loket | Riska   |
| Giffari Lestyonanda | B8   | 27  |      300.000 | 2025-04-09 | 2025-02 | 000879       | VA         | Loket | Riska   |
| Novita Anggraeni    | AM2  | 12  |      300.000 | 2025-04-09 | 2025-02 | 000881       | VA         | Loket | Riska   |
| Novita Anggraeni    | AX5  | 96  |      200.000 | 2025-04-09 | 2025-02 | 000882       | VA         | Loket | Riska   |
| Idrus               | E7   | 10  |      300.000 | 2025-04-09 | 2025-01 | 000883       | VA         | Loket | Riska   |
| Lady Diana          | C7   | 56  |      225.000 | 2025-04-09 | 2025-02 | 000887       | VA         | Loket | Riska   |
| Ronald Yahya        | C1   | 2   |      225.000 | 2025-04-09 | 2025-02 | 000889       | VA         | Loket | Riska   |
| Ita Hernita         | B10  | 57  |      300.000 | 2025-04-10 | 2024-05 | 000910       | Tunai      | Loket | Riska   |
| Mochamad Sachuri    | E7   | 25  |      300.000 | 2025-04-11 | 2025-01 | 000916       | VA         | Loket | Riska   |
| Endah Siwi          | C5   | 10  |      225.000 | 2025-04-14 | 2025-02 | 000943       | VA         | Loket | Riska   |
| Hasani              | B10  | 3   |      300.000 | 2025-04-14 | 2024-12 | 000946       | Transfer   | Loket | Riska   |
| Freddy Pribadi      | AX3  | 75  |      200.000 | 2025-04-15 | 2025-02 | 000962       | VA         | Loket | Riska   |
| Fransisca           | AX8  | 55  |      300.000 | 2025-04-16 | 2024-12 | 000968       | VA         | Loket | Riska   |
| Theresia Suyati     | AM2  | 11  |      300.000 | 2025-04-25 | 2025-02 | 001017       | VA         | Loket | Riska   |
| Meti oktavia        | DD2  | 2   |      250.000 | 2025-04-25 | 2025-05 | 001020       | Transfer   | Loket | Riska   |
| Liyanawati          | AX7  | 16  |      350.000 | 2025-04-25 | 2025-05 | 001022       | Transfer   | Loket | Riska   |
| Jhoni Candra        | C1   | 6   |      225.000 | 2025-04-25 | 2025-04 | 001025       | Transfer   | Loket | Riska   |
| Ronald              | DD1  | 3   |      250.000 | 2025-04-28 | 2025-02 | 001029       | VA         | Loket | Riska   |
| Karim Amirullah     | B10  | 16  |      300.000 | 2025-04-28 | 2025-02 | 001030       | VA         | Loket | Riska   |
| Jonathan            | AX3  | 5   |      200.000 | 2025-04-28 | 2024-10 | 001036       | Transfer   | Loket | Riska   |
| Etri Septivia       | B9   | 59  |      300.000 | 2025-04-28 | 2025-02 | 001040       | Transfer   | Loket | Riska   |
| Hansen              | B9   | 7   |      300.000 | 2025-04-28 | 2025-05 | 001041       | Transfer   | Loket | Riska   |
| Linda Karlinda      | JA   | 30  |      300.000 | 2025-04-28 | 2025-05 | 001043       | Transfer   | Loket | Riska   |
| Lukmanul Hakim      | C8   | 9   |      225.000 | 2025-04-29 | 2025-01 | 001048       | Transfer   | Loket | Riska   |
| Stefanus Andre      | AX9  | 50  |      300.000 | 2025-04-30 | 2025-05 | 001053       | Transfer   | Loket | Rsika   |
| Ferry Sofianto      | DD2  | 12  |      250.000 | 2025-04-30 | 2025-05 | 001055       | Transfer   | Loket | Rsika   |

## Mei 2025

| Nama                     | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC   | Petugas |
| ------------------------ | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ----- | ------- |
| Gani Elfana              | AX7  | 21  |      350.000 | 2025-05-02 | 2025-05 | 001058       | Transfer   | Loket | Riska   |
| Gani Elfana              | AM1  | 10  |      300.000 | 2025-05-02 | 2025-05 | 001059       | Transfer   | Loket | Riska   |
| Tanu Wijaya              | DD2  | 7   |      250.000 | 2025-05-02 | 2025-05 | 001061       | Transfer   | Loket | Riska   |
| Selvy                    | AX8  | 7   |      300.000 | 2025-05-02 | 2025-05 | 001063       | Transfer   | Loket | Riska   |
| Suryani                  | AM1  | 12  |      300.000 | 2025-05-02 | 2025-02 | 001066       | Tunai      | Loket | Riska   |
| Yusron Fauzi             | C8   | 12  |      225.000 | 2025-05-02 | 2025-05 | 001067       | Tunai      | Loket | Riska   |
| Julio Andrian            | AX3  | 29  |      200.000 | 2025-05-02 | 2025-05 | 001068       | Transfer   | Loket | Riska   |
| Albertus Chandra         | AX8  | 60  |      300.000 | 2025-05-02 | 2025-05 | 001069       | Transfer   | Loket | Riska   |
| Sandra Fivi              | AX8  | 11  |      300.000 | 2025-05-02 | 2025-02 | 001074       | Transfer   | Loket | Riska   |
| Indrawan                 | AX3  | 39  |      200.000 | 2025-05-05 | 2025-05 | 001075       | VA         | Loket | Riska   |
| Mohamad Arif             | E7   | 18  |      300.000 | 2025-05-05 | 2025-05 | 001076       | VA         | Loket | Riska   |
| Herman                   | DD3  | 10  |      250.000 | 2025-05-05 | 2025-05 | 001079       | VA         | Loket | Riska   |
| Chandra                  | AX6  | 19  |      350.000 | 2025-05-05 | 2025-05 | 001080       | VA         | Loket | Riska   |
| Drya Nursanti            | AX5  | 72  |      200.000 | 2025-05-05 | 2025-05 | 001081       | VA         | Loket | Riska   |
| Hanindya Puspita         | JA   | 5   |      300.000 | 2025-05-05 | 2025-05 | 001082       | VA         | Loket | Riska   |
| Multi Infiniti pro       | JA   | 8   |      300.000 | 2025-05-05 | 2025-05 | 001083       | VA         | Loket | Riska   |
| Evi Yulianti             | AX3  | 35  |      200.000 | 2025-05-05 | 2025-05 | 001087       | VA         | Loket | Riska   |
| Charlie Chandra          | AX5  | 38  |      200.000 | 2025-05-05 | 2025-05 | 001089       | VA         | Loket | Riska   |
| Jhon Delon               | AX5  | 78  |      200.000 | 2025-05-05 | 2025-05 | 001091       | Tunai      | Loket | Riska   |
| Bargani                  | C10  | 6   |      225.000 | 2025-05-05 | 2025-05 | 001095       | Tunai      | Loket | Arianto |
| Imas Rahayu Ningsih      | AX8  | 25  |      300.000 | 2025-05-05 | 2025-05 | 001098       | VA         | Loket | Riska   |
| Kartika Puspa            | AX7  | 8   |      350.000 | 2025-05-05 | 2025-05 | 001100       | VA         | Loket | Riska   |
| Eriyono Budi             | AX3  | 69  |      200.000 | 2025-05-05 | 2025-05 | 001101       | VA         | Loket | Riska   |
| Leo Baratanata           | DD1  | 15  |      250.000 | 2025-05-05 | 2025-05 | 001102       | VA         | Loket | Riska   |
| Melina                   | AX8  | 36  |      300.000 | 2025-05-05 | 2025-05 | 001106       | VA         | Loket | Riska   |
| Noviana                  | AX7  | 33  |      350.000 | 2025-05-05 | 2025-05 | 001107       | VA         | Loket | Riska   |
| Elroys Oktavianus        | B10  | 63  |      300.000 | 2025-05-05 | 2025-05 | 001108       | VA         | Loket | Riska   |
| Noviani Harianto         | C11  | 10  |      225.000 | 2025-05-05 | 2025-05 | 001109       | Transfer   | Loket | Riska   |
| Luman                    | AX7  | 37  |      350.000 | 2025-05-05 | 2025-05 | 001110       | Transfer   | Loket | Riska   |
| Stanley Giovanni         | AX5  | 23  |      250.000 | 2025-05-05 | 2025-05 | 001115       | Transfer   | Loket | Riska   |
| Siti Suprapti            | AX2  | 8&9 |      500.000 | 2025-05-05 | 2025-05 | 001116       | Transfer   | Loket | Riska   |
| Dessy                    | AX7  | 55  |      350.000 | 2025-05-05 | 2025-05 | 001117       | Transfer   | Loket | Riska   |
| Budhi Mulyadi            | DD3  | 3   |      250.000 | 2025-05-05 | 2025-05 | 001118       | Transfer   | Loket | Riska   |
| Sapto Rini Rushapsari    | AX6  | 7   |      350.000 | 2025-05-05 | 2025-05 | 001121       | Transfer   | Loket | Riska   |
| Dendy                    | AX3  | 91  |      200.000 | 2025-05-05 | 2025-05 | 001122       | Transfer   | Loket | Riska   |
| Anter Aja                | JA   | 15  |      300.000 | 2025-05-05 | 2025-05 | 001123       | Tunai      | Loket | Riska   |
| Lusia Tungadi            | D8   | 11  |      350.000 | 2025-05-05 | 2025-05 | 001125       | Transfer   | Loket | Riska   |
| Deri Yatno               | AX7  | 29  |      350.000 | 2025-05-06 | 2025-05 | 001127       | VA         | Loket | Arianto |
| Rika                     | AM2  | 10  |      300.000 | 2025-05-06 | 2025-05 | 001129       | VA         | Loket | Arianto |
| Devi Febrian             | DD1  | 18  |      250.000 | 2025-05-06 | 2025-05 | 001130       | VA         | Loket | Arianto |
| Vivin Rosalia            | C10  | 2   |      225.000 | 2025-05-06 | 2025-05 | 001132       | VA         | Loket | Arianto |
| Budiman                  | C6   | 61  |      225.000 | 2025-05-06 | 2024-10 | 001134       | Tunai      | Loket | Arianto |
| Hans Jakal Perdana       | C1   | 12  |      225.000 | 2025-05-06 | 2025-05 | 001135       | Tunai      | Loket | Arianto |
| Evi / GBI                | JA   | 1   |      300.000 | 2025-05-06 | 2025-05 | 001136       | Transfer   | Loket | Arianto |
| Viyan Andhika            | AX7  | 19  |      350.000 | 2025-05-06 | 2025-05 | 001139       | Transfer   | Loket | Riska   |
| Indomaret Grand Duta 1   | JA   | 10  |      300.000 | 2025-05-06 | 2025-05 | 001141       | Tunai      | Loket | Riska   |
| Indomaret Grand Duta 1   | JA   | 11  |      300.000 | 2025-05-06 | 2025-05 | 001142       | Tunai      | Loket | Riska   |
| Rinaldy Tarmadi          | AX5  | 39  |      250.000 | 2025-05-07 | 2025-05 | 001143       | VA         | Loket | Riska   |
| Ldia Apriani             | AX5  | 70  |      200.000 | 2025-05-07 | 2025-05 | 001146       | VA         | Loket | Riska   |
| Kuswanto                 | AM2  | 19  |      300.000 | 2025-05-07 | 2025-05 | 001148       | VA         | Loket | Riska   |
| Nurhapsah                | AM1  | 5   |      300.000 | 2025-05-07 | 2025-05 | 001149       | VA         | Loket | Riska   |
| Ervina                   | AM1  | 3   |      300.000 | 2025-05-07 | 2025-05 | 001151       | VA         | Loket | Riska   |
| Dedi Sugiarto            | DD1  | 19  |      250.000 | 2025-05-07 | 2025-05 | 001152       | VA         | Loket | Riska   |
| Tain Yunan               | AM1  | 2   |      300.000 | 2025-05-07 | 2025-05 | 001153       | VA         | Loket | Riska   |
| Wirya Dharmawan          | AX3  | 71  |      200.000 | 2025-05-07 | 2025-05 | 001155       | Transfer   | Loket | Riska   |
| Wulan Januri             | C6   | 37  |      225.000 | 2025-05-07 | 2025-05 | 001159       | Transfer   | Loket | Riska   |
| Lisa Loandha             | C11  | 20  |      225.000 | 2025-05-07 | 2025-05 | 001160       | Transfer   | Loket | Riska   |
| Bambang Supriyadi        | C7   | 38  |      225.000 | 2025-05-08 | 2025-05 | 001166       | VA         | Loket | Riska   |
| Danang Riyadi            | AX8  | 51  |      300.000 | 2025-05-08 | 2025-05 | 001167       | VA         | Loket | Riska   |
| Andri Mustakim           | AX5  | 5   |      250.000 | 2025-05-08 | 2025-05 | 001168       | Transfer   | Loket | Riska   |
| Verawati Wijaya          | AX5  | 18  |      200.000 | 2025-05-09 | 2025-05 | 001172       | VA         | Loket | Riska   |
| M Alfian Khairi          | C9   | 7   |      225.000 | 2025-05-09 | 2025-05 | 001174       | Transfer   | Loket | Riska   |
| Abdul Azis Saifuddin     | C11  | 8   |      225.000 | 2025-05-09 | 2025-05 | 001175       | Transfer   | Loket | Riska   |
| Dewi Santosari           | C5   | 9   |      225.000 | 2025-05-13 | 2025-05 | 001179       | VA         | Loket | Riska   |
| Agnes Wiwik              | B10  | 77  |      300.000 | 2025-05-13 | 2025-05 | 001180       | VA         | Loket | Riska   |
| Anastasia Dame           | AX7  | 7   |      350.000 | 2025-05-13 | 2025-05 | 001181       | VA         | Loket | Riska   |
| Siaw Ling                | AX3  | 89  |      200.000 | 2025-05-13 | 2025-05 | 001182       | VA         | Loket | Riska   |
| Nicholas Widjaja         | DD1  | 7   |      250.000 | 2025-05-13 | 2025-05 | 001183       | VA         | Loket | Riska   |
| Else                     | DD1  | 2   |      250.000 | 2025-05-13 | 2025-05 | 001184       | VA         | Loket | Riska   |
| Yuki Erik                | B10  | 8   |      300.000 | 2025-05-13 | 2025-05 | 001185       | VA         | Loket | Riska   |
| Sonya Elia Chandra       | AX5  | 20  |      200.000 | 2025-05-13 | 2025-05 | 001193       | Transfer   | Loket | Riska   |
| Muhammad Fochdilah       | AX6  | 70  |      250.000 | 2025-05-13 | 2025-05 | 001195       | Transfer   | Loket | Riska   |
| Herson                   | D8   | 3   |      350.000 | 2025-05-13 | 2025-05 | 001196       | Transfer   | Loket | Riska   |
| Herson                   | D8   | 7   |      350.000 | 2025-05-13 | 2025-05 | 001197       | Transfer   | Loket | Riska   |
| Herson                   | C7   | 28  |      225.000 | 2025-05-13 | 2025-05 | 001201       | Transfer   | Loket | Riska   |
| Arnold Dwi Putra         | AX5  | 26  |      200.000 | 2025-05-13 | 2025-05 | 001203       | Transfer   | Loket | Riska   |
| Elly Swanti              | C1   | 11  |      225.000 | 2025-05-14 | 2025-05 | 001206       | VA         | Loket | Riska   |
| Yuliawati                | AX8  | 6   |      300.000 | 2025-05-14 | 2025-05 | 001208       | Transfer   | Loket | Riska   |
| Kristianto               | JA   | 2   |      300.000 | 2025-05-14 | 2025-05 | 001209       | Tunai      | Loket | Riska   |
| Seftiningtias            | AX3  | 17  |      200.000 | 2025-05-14 | 2025-05 | 001211       | Transfer   | Loket | Riska   |
| Henni Johanes            | AX5  | 59  |      250.000 | 2025-05-14 | 2025-05 | 001213       | Transfer   | Loket | Riska   |
| Nova Diana               | B10  | 6   |      300.000 | 2025-05-14 | 2025-05 | 001216       | Transfer   | Loket | Riska   |
| Friska                   | AX5  | 57  |      250.000 | 2025-05-15 | 2025-05 | 001218       | Tunai      | Loket | Riska   |
| Hendry                   | DD1  | 9   |      250.000 | 2025-05-16 | 2025-05 | 001226       | VA         | Loket | Riska   |
| Yudian Fantoni           | B9   | 36  |      300.000 | 2025-05-16 | 2025-05 | 001227       | Tunai      | Loket | Riska   |
| Dila Ardila              | B10  | 25  |      300.000 | 2025-05-16 | 2025-05 | 001228       | VA         | Loket | Riska   |
| Hadiyanto                | C8   | 11  |      225.000 | 2025-05-16 | 2025-05 | 001229       | VA         | Loket | Riska   |
| Sabeth                   | AX8  | 30  |      300.000 | 2025-05-16 | 2025-05 | 001230       | VA         | Loket | Riska   |
| Marliana Sari            | AX5  | 6   |      200.000 | 2025-05-16 | 2025-05 | 001231       | Tunai      | Loket | Riska   |
| Jangkau Lintas Nusantara | E7   | 50  |      300.000 | 2025-05-16 | 2025-05 | 001234       | Transfer   | Loket | Riska   |
| Friciel Yael             | B8   | 79  |      300.000 | 2025-05-19 | 2025-05 | 001238       | VA         | Loket | Riska   |
| Siti Nurwati             | AX6  | 17  |      350.000 | 2025-05-19 | 2025-05 | 001239       | VA         | Loket | Riska   |
| Arlia Anwar              | B10  | 78  |      300.000 | 2025-05-19 | 2025-05 | 001241       | VA         | Loket | Riska   |
| Rosia Daeli              | C6   | 27  |      225.000 | 2025-05-19 | 2025-05 | 001244       | VA         | Loket | Riska   |
| Iwan Kiswanto            | B7   | 39  |      300.000 | 2025-05-19 | 2025-05 | 001245       | Transfer   | Loket | Riska   |
| GSJA Abbacare            | AL2  | 12  |      300.000 | 2025-05-19 | 2025-05 | 001247       | Transfer   | Loket | Riska   |
| Anugerah Primarasa       | DD3  | 6   |      250.000 | 2025-05-19 | 2025-05 | 001248       | Transfer   | Loket | Riska   |
| Fransisca Herlin         | B8   | 35  |      300.000 | 2025-05-20 | 2025-05 | 001251       | VA         | Loket | Riska   |
| Riyandi Susanto          | C11  | 28  |      225.000 | 2025-05-20 | 2025-05 | 001256       | Transfer   | Loket | Riska   |
| Siti fatonah             | AX5  | 32  |      200.000 | 2025-05-21 | 2025-05 | 001259       | Tunai      | Loket | Riska   |
| Nico Cristian            | E7   | 8   |      300.000 | 2025-05-22 | 2025-05 | 001268       | Transfer   | Loket | Riska   |
| Pie Sian                 | AX8  | 62  |      300.000 | 2025-05-23 | 2025-05 | 001270       | VA         | Loket | Riska   |
| Ediana Sigalingging      | AM2  | 1   |      300.000 | 2025-05-23 | 2025-05 | 001273       | Tunai      | Loket | Riska   |
| Ediana Sigalingging      | AM2  | 2   |      300.000 | 2025-05-23 | 2025-05 | 001274       | Tunai      | Loket | Riska   |
| Jonothan                 | AX3  | 5   |      200.000 | 2025-05-23 | 2025-01 | 001276       | Transfer   | Loket | Riska   |
| Handrian Wijaya          | DD1  | 5   |      250.000 | 2025-05-23 | 2025-05 | 001277       | Transfer   | Loket | Riska   |
| Liyanawati               | AX7  | 16  |      350.000 | 2025-05-26 | 2025-06 | 001289       | Transfer   | Loket | Riska   |
| Meti Oktavia             | DD2  | 2   |      250.000 | 2025-05-26 | 2025-06 | 001295       | Transfer   | Loket | Riska   |
| Nisrin                   | B10  | 7   |      300.000 | 2025-05-26 | 2025-02 | 001298       | Transfer   | Loket | Riska   |
| Jhoni Candra             | C1   | 6   |      225.000 | 2025-05-27 | 2025-05 | 001307       | Transfer   | Loket | Riska   |
| Stephanie Setiawan       | DD1  | 6   |      250.000 | 2025-05-27 | 2025-05 | 001309       | Transfer   | Loket | Riska   |
| Ikhwan Rosyiidian        | AX8  | 52  |      300.000 | 2025-05-27 | 2025-05 | 001310       | Transfer   | Loket | Riska   |
| Hon King Wie             | C2   | 20  |      225.000 | 2025-05-27 | 2025-05 | 001312       | Transfer   | Loket | Riska   |
| Sahrial Lubis            | C2   | 27  |      225.000 | 2025-05-28 | 2025-05 | 001314       | Tunai      | Loket | Riska   |
| Mohamad Yusuf            | B8   | 81  |      300.000 | 2025-05-28 | 2025-05 | 001319       | Transfer   | Loket | Riska   |
| Stefanus Andre           | AX9  | 50  |      300.000 | 2025-05-28 | 2025-06 | 001321       | Transfer   | Loket | Riska   |

## Juni 2025

| Nama                     | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC   | Petugas |
| ------------------------ | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ----- | ------- |
| Ferry Sofianto           | DD2  | 12  |      250.000 | 2025-06-02 | 2025-06 | 001329       | Transfer   | Loket | Riska   |
| Lukmanul Hakim           | C8   | 9   |      225.000 | 2025-06-02 | 2025-02 | 001330       | Transfer   | Loket | Riska   |
| Badai Rosamudra          | AX5  | 56  |      200.000 | 2025-06-02 | 2025-05 | 001331       | Transfer   | Loket | Riska   |
| Hansen                   | B9   | 7   |      300.000 | 2025-06-02 | 2025-06 | 001332       | Transfer   | Loket | Riska   |
| Ernest Kurniawan         | DD2  | 8   |      250.000 | 2025-06-02 | 2025-05 | 001333       | Transfer   | Loket | Riska   |
| Wulan Januri             | C6   | 37  |      225.000 | 2025-06-02 | 2025-06 | 001334       | Transfer   | Loket | Riska   |
| Benny Supardjo           | C6   | 29  |      225.000 | 2025-06-02 | 2025-05 | 001335       | Transfer   | Loket | Riska   |
| Evi Yulianti             | AX3  | 35  |      200.000 | 2025-06-02 | 2025-06 | 001339       | Transfer   | Loket | Riska   |
| Carmel Anugerah          | E7   | 29  |      300.000 | 2025-06-02 | 2025-05 | 001341       | Transfer   | Loket | Riska   |
| Indomaret grand Duta 2   | E7   | 27  |      300.000 | 2025-06-02 | 2025-05 | 001342       | Tunai      | Loket | Riska   |
| Indomaret grand Duta 2   | E7   | 28  |      300.000 | 2025-06-02 | 2025-05 | 001343       | Tunai      | Loket | Riska   |
| Johanna Hermawan         | DD2  | 3   |      250.000 | 2025-06-02 | 2025-05 | 001344       | Transfer   | Loket | Riska   |
| Rangga Afyan Dwiokta     | AX6  | 62  |      250.000 | 2025-06-02 | 2025-05 | 001345       | Transfer   | Loket | Riska   |
| Pramadasyah              | B9   | 32  |      300.000 | 2025-06-02 | 2025-05 | 001346       | Transfer   | Loket | Riska   |
| Tanu Wijaya              | DD2  | 7   |      250.000 | 2025-06-02 | 2025-06 | 001347       | Transfer   | Loket | Riska   |
| Budhi Mulyadi            | DD3  | 3   |      250.000 | 2025-06-02 | 2025-06 | 001348       | Transfer   | Loket | Riska   |
| Julio Andrian            | AX3  | 29  |      200.000 | 2025-06-02 | 2025-06 | 001349       | Transfer   | Loket | Riska   |
| Selvy                    | AX8  | 7   |      300.000 | 2025-06-02 | 2025-06 | 001350       | Transfer   | Loket | Riska   |
| Dalyano Kayambo          | AX3  | 81  |      200.000 | 2025-06-02 | 2025-02 | 001353       | Transfer   | Loket | Riska   |
| Anugerah Primarasa       | DD3  | 6   |      250.000 | 2025-06-02 | 2025-06 | 001354       | Transfer   | Loket | Riska   |
| Novriyansyah Karna       | AM1  | 11  |      300.000 | 2025-06-02 | 2025-05 | 001355       | Transfer   | Loket | Riska   |
| Kristianto               | JA   | 2   |      300.000 | 2025-06-03 | 2025-06 | 001357       | Tunai      | Loket | Riska   |
| Jhon Delon               | AX5  | 78  |      200.000 | 2025-06-03 | 2025-06 | 001358       | Tunai      | Loket | Riska   |
| Yusron Fauzi             | C8   | 12  |      225.000 | 2025-06-03 | 2025-06 | 001360       | Tunai      | Loket | Riska   |
| Putri Alvina             | AX7  | 27  |      350.000 | 2025-06-03 | 2025-06 | 001363       | Transfer   | Loket | Riska   |
| Putri Alvina             | JA   | 18  |      300.000 | 2025-06-03 | 2025-06 | 001364       | Transfer   | Loket | Riska   |
| Sapto rini               | AX6  | 7   |      350.000 | 2025-06-03 | 2025-06 | 001365       | Transfer   | Loket | Riska   |
| Nani Junaeni             | AX8  | 19  |      300.000 | 2025-06-03 | 2025-05 | 001366       | Transfer   | Loket | Riska   |
| Lilih Yuliani            | AX5  | 36  |      200.000 | 2025-06-03 | 2025-05 | 001368       | Transfer   | Loket | Riska   |
| Siti Suprapti            | AX2  | 8&9 |      500.000 | 2025-06-03 | 2025-06 | 001370       | Transfer   | Loket | Riska   |
| Noviani Harianto         | C11  | 10  |      225.000 | 2025-06-03 | 2025-06 | 001371       | Transfer   | Loket | Riska   |
| Wirya Dharmawan          | AX3  | 71  |      200.000 | 2025-06-03 | 2025-06 | 001372       | Transfer   | Loket | Riska   |
| Indomaret Gran duta 1    | JA   | 10  |      300.000 | 2025-06-03 | 2025-06 | 001373       | Tunai      | Loket | Riska   |
| Indomaret Gran duta 1    | JA   | 11  |      300.000 | 2025-06-03 | 2025-06 | 001374       | Tunai      | Loket | Riska   |
| Anter Aja                | JA   | 15  |      300.000 | 2025-06-04 | 2025-06 | 001375       | Tunai      | Loket | Riska   |
| Purnawati SE             | AX6  | 9   |      350.000 | 2025-06-04 | 2025-05 | 001376       | Tunai      | Loket | Riska   |
| Evi / GBI                | JA   | 1   |      300.000 | 2025-06-04 | 2025-06 | 001377       | Transfer   | Loket | Riska   |
| Luman                    | AX7  | 37  |      350.000 | 2025-06-04 | 2025-06 | 001378       | Transfer   | Loket | Riska   |
| Nova Diana               | B10  | 6   |      300.000 | 2025-06-04 | 2025-06 | 001379       | Transfer   | Loket | Riska   |
| Sinta Dewi               | AX5  | 33  |      250.000 | 2025-06-04 | 2025-05 | 001380       | Transfer   | Loket | Riska   |
| Tio Fani                 | AX8  | 6   |      300.000 | 2025-06-04 | 2025-06 | 001381       | Transfer   | Loket | Riska   |
| Vina Karya               | AL2  | 11  |      300.000 | 2025-06-05 | 2025-05 | 001383       | Tunai      | Loket | Ickna   |
| Indrawan                 | AX3  | 39  |      200.000 | 2025-06-05 | 2025-06 | 001386       | VA         | Loket | Riska   |
| Deri Yatno               | AX7  | 29  |      350.000 | 2025-06-05 | 2025-06 | 001387       | VA         | Loket | Riska   |
| Arlia Anwar              | B10  | 78  |      300.000 | 2025-06-05 | 2025-06 | 001388       | VA         | Loket | Riska   |
| Kartika Puspa            | AX7  | 8   |      350.000 | 2025-06-05 | 2025-06 | 001389       | VA         | Loket | Riska   |
| Dedi Sugiarto            | DD1  | 19  |      250.000 | 2025-06-05 | 2025-06 | 001390       | VA         | Loket | Riska   |
| Chandra                  | AX6  | 19  |      350.000 | 2025-06-05 | 2025-06 | 001391       | VA         | Loket | Riska   |
| Tain Yunan               | AM1  | 2   |      300.000 | 2025-06-05 | 2025-06 | 001393       | VA         | Loket | Riska   |
| Herman                   | DD3  | 10  |      250.000 | 2025-06-05 | 2025-06 | 001394       | VA         | Loket | Riska   |
| Charlie Chandra          | AX5  | 38  |      200.000 | 2025-06-05 | 2025-06 | 001395       | VA         | Loket | Riska   |
| Multi Infiniti Pro       | JA   | 8   |      300.000 | 2025-06-05 | 2025-06 | 001396       | VA         | Loket | Riska   |
| Drya Nursanti            | AX5  | 72  |      200.000 | 2025-06-05 | 2025-06 | 001397       | VA         | Loket | Riska   |
| Melina                   | AX8  | 36  |      300.000 | 2025-06-05 | 2025-06 | 001399       | VA         | Loket | Riska   |
| Noviana                  | AX7  | 33  |      350.000 | 2025-06-05 | 2025-06 | 001400       | VA         | Loket | Riska   |
| Lukimin                  | AX3  | 23  |      200.000 | 2025-06-05 | 2025-06 | 001401       | VA         | Loket | Riska   |
| Yenni                    | C9   | 6   |      225.000 | 2025-06-05 | 2025-06 | 001402       | VA         | Loket | Riska   |
| Regina Adytia            | AX8  | 3   |      300.000 | 2025-06-05 | 2025-06 | 001403       | VA         | Loket | Riska   |
| Lista Akdriaty           | C3   | 7   |      225.000 | 2025-06-05 | 2025-06 | 001404       | VA         | Loket | Riska   |
| Rita                     | B10  | 21  |      300.000 | 2025-06-05 | 2025-06 | 001405       | VA         | Loket | Riska   |
| Hans Jakal Perdana       | C1   | 12  |      225.000 | 2025-06-05 | 2025-06 | 001406       | Tunai      | Loket | Riska   |
| Viyan Andhika            | AX7  | 19  |      350.000 | 2025-06-05 | 2025-06 | 001407       | Transfer   | Loket | Riska   |
| Gani Elfana              | AX7  | 21  |      350.000 | 2025-06-05 | 2025-06 | 001408       | Transfer   | Loket | Riska   |
| Gani Elfana              | AM1  | 10  |      300.000 | 2025-06-05 | 2025-06 | 001409       | Transfer   | Loket | Riska   |
| Muhamad Fachdilah        | AX6  | 70  |      250.000 | 2025-06-05 | 2025-06 | 001410       | Transfer   | Loket | Riska   |
| Lidia Apriani            | AX5  | 70  |      200.000 | 2025-06-05 | 2025-06 | 001412       | Transfer   | Loket | Riska   |
| Andri Mustakim           | AX5  | 5   |      250.000 | 2025-06-05 | 2025-06 | 001413       | Transfer   | Loket | Riska   |
| Rully Angga              | AX7  | 12  |      350.000 | 2025-06-09 | 2025-06 | 001416       | VA         | Loket | Riska   |
| Cedy Chandra             | AX6  | 3   |      350.000 | 2025-06-09 | 2025-06 | 001417       | VA         | Loket | Riska   |
| Mohamad Arif             | E7   | 18  |      300.000 | 2025-06-09 | 2025-06 | 001418       | VA         | Loket | Riska   |
| Verawati Wijaya          | AX5  | 18  |      200.000 | 2025-06-09 | 2025-06 | 001419       | VA         | Loket | Riska   |
| Linda Wati               | B10  | 39  |      300.000 | 2025-06-09 | 2025-06 | 001421       | VA         | Loket | Riska   |
| Eriyono Budi             | AX3  | 69  |      200.000 | 2025-06-09 | 2025-06 | 001422       | VA         | Loket | Riska   |
| Leo Baratanata           | DD1  | 15  |      250.000 | 2025-06-09 | 2025-06 | 001423       | VA         | Loket | Riska   |
| Elroys Oktavianus        | B10  | 63  |      300.000 | 2025-06-09 | 2025-06 | 001425       | VA         | Loket | Riska   |
| Hanindya Puspita         | JA   | 5   |      300.000 | 2025-06-09 | 2025-06 | 001426       | VA         | Loket | Riska   |
| Friska                   | AX5  | 57  |      250.000 | 2025-06-09 | 2025-06 | 001427       | Tunai      | Loket | Riska   |
| Albertus Chandra         | AX8  | 60  |      300.000 | 2025-06-09 | 2025-06 | 001429       | Transfer   | Loket | Riska   |
| Stanley Giovanni         | AX5  | 23  |      250.000 | 2025-06-09 | 2025-06 | 001431       | Transfer   | Loket | Riska   |
| Reza Satria              | AX6  | 66  |      250.000 | 2025-06-09 | 2025-06 | 001433       | Transfer   | Loket | Riska   |
| Lisa Loandha             | C11  | 20  |      225.000 | 2025-06-09 | 2025-06 | 001434       | Transfer   | Loket | Riska   |
| Noer Hartati             | C6   | 59  |      225.000 | 2025-06-10 | 2025-06 | 001437       | VA         | Loket | Riska   |
| Elly Swanti              | C1   | 11  |      225.000 | 2025-06-10 | 2025-06 | 001439       | VA         | Loket | Riska   |
| Bambang Supriyadi        | C7   | 38  |      225.000 | 2025-06-10 | 2025-06 | 001440       | VA         | Loket | Riska   |
| Dilla Ardila             | B10  | 25  |      300.000 | 2025-06-10 | 2025-06 | 001441       | VA         | Loket | Riska   |
| Kuswanto                 | AM2  | 19  |      300.000 | 2025-06-10 | 2025-06 | 001442       | VA         | Loket | Riska   |
| Anista Retno             | JA   | 16  |      300.000 | 2025-06-10 | 2025-05 | 001444       | Transfer   | Loket | Riska   |
| Tri Septiyanti           | E7   | 7   |      300.000 | 2025-06-10 | 2025-05 | 001445       | Transfer   | Loket | Riska   |
| M Alfian Khairi          | C9   | 7   |      225.000 | 2025-06-10 | 2025-06 | 001446       | Transfer   | Loket | Riska   |
| Abdul Azis Saifuddin     | C11  | 8   |      225.000 | 2025-06-10 | 2025-06 | 001447       | Transfer   | Loket | Riska   |
| Diah Fajar Sari          | C1   | 5   |      225.000 | 2025-06-11 | 2025-06 | 001450       | Transfer   | Loket | Rischa  |
| Ervina                   | AM1  | 3   |      300.000 | 2025-06-11 | 2025-06 | 001451       | VA         | Loket | Rischa  |
| Nurhapsah                | AM1  | 5   |      300.000 | 2025-06-11 | 2025-06 | 001452       | VA         | Loket | Rischa  |
| Devi Febrian             | DD1  | 18  |      250.000 | 2025-06-11 | 2025-06 | 001453       | VA         | Loket | Rischa  |
| Nicholas Widjaja         | DD1  | 7   |      250.000 | 2025-06-11 | 2025-06 | 001454       | VA         | Loket | Rischa  |
| Anastasia Dame           | AX7  | 7   |      350.000 | 2025-06-11 | 2025-06 | 001455       | VA         | Loket | Rischa  |
| Seftiningtyas            | AX3  | 17  |      200.000 | 2025-06-11 | 2025-06 | 001458       | Transfer   | Loket | Rischa  |
| Christy Veronika         | B10  | 3   |      300.000 | 2025-06-11 | 2025-01 | 001459       | Transfer   | Loket | Rischa  |
| Jaya Iskandar            | C9   | 8   |      225.000 | 2025-06-12 | 2025-05 | 001462       | Tunai      | Loket | Riska   |
| Siaw Ling                | AX3  | 89  |      200.000 | 2025-06-12 | 2025-06 | 001463       | Tunai      | Loket | Riska   |
| Yuki Erik                | B10  | 8   |      300.000 | 2025-06-12 | 2025-06 | 001464       | VA         | Loket | Riska   |
| Rika                     | AM2  | 10  |      300.000 | 2025-06-12 | 2025-06 | 001465       | VA         | Loket | Riska   |
| Herson                   | D8   | 3   |      350.000 | 2025-06-12 | 2025-06 | 001467       | Transfer   | Loket | Riska   |
| Herson                   | D8   | 7   |      350.000 | 2025-06-12 | 2025-06 | 001468       | Transfer   | Loket | Riska   |
| Herson                   | AX9  | 38  |      300.000 | 2025-06-12 | 2025-05 | 001469       | Transfer   | Loket | Riska   |
| Herson                   | DD2  | 19  |      250.000 | 2025-06-12 | 2025-05 | 001470       | Transfer   | Loket | Riska   |
| Herson                   | C6   | 55  |      225.000 | 2025-06-12 | 2025-05 | 001471       | Transfer   | Loket | Riska   |
| Herson                   | C7   | 28  |      225.000 | 2025-06-12 | 2025-06 | 001472       | Transfer   | Loket | Riska   |
| Herson                   | AL2  | 5   |      300.000 | 2025-06-12 | 2025-05 | 001473       | Transfer   | Loket | Riska   |
| Sonya Elia Chandra       | AX5  | 20  |      200.000 | 2025-06-12 | 2025-06 | 001474       | Transfer   | Loket | Riska   |
| Henni Johanes            | AX5  | 59  |      250.000 | 2025-06-12 | 2025-06 | 001475       | Transfer   | Loket | Riska   |
| GSJA Abbacare            | AL2  | 12  |      300.000 | 2025-06-12 | 2025-06 | 001477       | Transfer   | Loket | Riska   |
| Lusia tungadi            | D8   | 11  |      350.000 | 2025-06-12 | 2025-06 | 001479       | Transfer   | Loket | Riska   |
| Johanna Hermawan         | DD2  | 3   |      250.000 | 2025-06-12 | 2025-06 | 001480       | Transfer   | Loket | Riska   |
| Agnes Wiwik              | B10  | 77  |      300.000 | 2025-06-13 | 2025-06 | 001482       | VA         | Loket | Riska   |
| Teguh Santoso            | C3   | 3   |      225.000 | 2025-06-13 | 2025-06 | 001483       | VA         | Loket | Riska   |
| Nico Cristian            | E7   | 8   |      300.000 | 2025-06-13 | 2025-06 | 001484       | VA         | Loket | Riska   |
| Arnold Dwi Putra         | AX5  | 26  |      200.000 | 2025-06-13 | 2025-06 | 001485       | VA         | Loket | Riska   |
| Else                     | DD1  | 2   |      250.000 | 2025-06-13 | 2025-06 | 001486       | VA         | Loket | Riska   |
| Rosia Daeli              | C6   | 27  |      225.000 | 2025-06-13 | 2025-06 | 001487       | VA         | Loket | Riska   |
| Siti Nurwati             | AX6  | 17  |      350.000 | 2025-06-13 | 2025-06 | 001488       | VA         | Loket | Riska   |
| Friciel Yael             | B8   | 79  |      300.000 | 2025-06-13 | 2025-06 | 001489       | Transfer   | Loket | Riska   |
| Yani Lestari             | AX7  | 6   |      350.000 | 2025-06-16 | 2025-06 | 001490       | VA         | Loket | Riska   |
| Rinaldy Tarmadi          | AX5  | 39  |      250.000 | 2025-06-16 | 2025-06 | 001493       | VA         | Loket | Riska   |
| Jangkau Lintar Nusantara | E7   | 50  |      300.000 | 2025-06-16 | 2025-06 | 001494       | Transfer   | Loket | Riska   |
| ME Diah Ayu              | B10  | 27  |      300.000 | 2025-06-16 | 2025-05 | 001503       | Transfer   | Loket | Riska   |
| Candra Martadinata       | JA   | 3   |      300.000 | 2025-06-16 | 2025-05 | 001504       | Transfer   | Loket | Riska   |
| Candra Martadinata       | AL2  | 10  |      300.000 | 2025-06-16 | 2025-05 | 001505       | Transfer   | Loket | Riska   |
| Sabeth                   | AX8  | 30  |      300.000 | 2025-06-17 | 2025-06 | 001509       | VA         | Loket | Riska   |
| Klorista Ika             | AX7  | 5   |      350.000 | 2025-06-17 | 2025-06 | 001510       | VA         | Loket | Riska   |
| Hendry                   | DD1  | 9   |      250.000 | 2025-06-17 | 2025-06 | 001511       | VA         | Loket | Riska   |
| Carmel Anugrah           | E7   | 29  |      300.000 | 2025-06-17 | 2025-06 | 001513       | Transfer   | Loket | Riska   |
| Giffari Lestyonanda      | B8   | 27  |      300.000 | 2025-06-18 | 2025-06 | 001515       | VA         | Loket | Riska   |
| Rizky Arif               | AX8  | 5   |      300.000 | 2025-06-18 | 2025-05 | 001517       | Tunai      | Loket | Riska   |
| Pie Sian                 | AX8  | 62  |      300.000 | 2025-06-19 | 2025-06 | 001520       | VA         | Loket | Riska   |
| Fransisca Herlin         | B8   | 35  |      300.000 | 2025-06-19 | 2025-06 | 001521       | VA         | Loket | Riska   |
| Bargani                  | C10  | 6   |      225.000 | 2025-06-20 | 2025-06 | 001526       | Tunai      | Loket | Riska   |
| Fendy Surya              | AX6  | 28  |      250.000 | 2025-06-20 | 2025-12 | 001527       | Tunai      | Loket | Riska   |
| Imas Rahayu Ningsih      | AX8  | 25  |      300.000 | 2025-06-20 | 2025-06 | 001529       | VA         | Loket | Riska   |
| Yulin                    | AX8  | 39  |      300.000 | 2025-06-23 | 2025-06 | 001533       | VA         | Loket | Riska   |
| Ajeng                    | DD1  | 17  |      250.000 | 2025-06-23 | 2025-06 | 001535       | VA         | Loket | Riska   |
| Dewi Santosari           | C5   | 9   |      225.000 | 2025-06-23 | 2025-06 | 001536       | VA         | Loket | Riska   |
| Dessy                    | AX7  | 55  |      350.000 | 2025-06-23 | 2025-06 | 001537       | VA         | Loket | Riska   |
| Dendy                    | AX3  | 91  |      200.000 | 2025-06-23 | 2025-06 | 001538       | Transfer   | Loket | Riska   |
| Rohani Susi Herlin       | B9   | 10  |      300.000 | 2025-06-23 | 2025-06 | 001539       | Transfer   | Loket | Riska   |
| Edy Hartono              | JA   | 19  |      300.000 | 2025-06-24 | 2025-06 | 001542       | VA         | Loket | Riska   |
| Marliana Sari            | AX5  | 6   |      200.000 | 2025-06-24 | 2025-06 | 001544       | Tunai      | Loket | Riska   |
| Riyandi Susanto          | C11  | 28  |      225.000 | 2025-06-24 | 2025-06 | 001546       | Transfer   | Loket | Riska   |
| Hary                     | C9   | 11  |      225.000 | 2025-06-24 | 2024-12 | 001547       | Transfer   | Loket | Riska   |
| Hadianto                 | C8   | 11  |      225.000 | 2025-06-25 | 2025-06 | 001548       | VA         | Loket | Riska   |
| Siti Fatonah             | AX5  | 32  |      200.000 | 2025-06-25 | 2025-06 | 001549       | Tunai      | Loket | Riska   |
| Henry Tulus              | DD2  | 5   |      250.000 | 2025-06-25 | 2025-06 | 001553       | Transfer   | Loket | Riska   |
| Meti Oktavia             | DD2  | 2   |      250.000 | 2025-06-25 | 2025-07 | 001554       | Transfer   | Loket | Riska   |
| Ikhwan Rosyiidian        | AX8  | 52  |      300.000 | 2025-06-25 | 2025-06 | 001555       | Transfer   | Loket | Riska   |
| Andrew                   | C8   | 3   |      225.000 | 2025-06-25 | 2025-05 | 001556       | Transfer   | Loket | Riska   |
| Jonathan                 | AX3  | 5   |      200.000 | 2025-06-25 | 2025-02 | 001558       | Transfer   | Loket | Riska   |
| Sahrial Lubis            | C2   | 27  |      225.000 | 2025-06-26 | 2025-06 | 001559       | Tunai      | Loket | Riska   |
| Nesa Apriari             | B8   | 67  |      300.000 | 2025-06-26 | 2025-06 | 001560       | VA         | Loket | Riska   |
| Ronald Yahya             | C1   | 2   |      225.000 | 2025-06-26 | 2025-06 | 001561       | VA         | Loket | Riska   |
| Linda Karlinda           | JA   | 30  |      300.000 | 2025-06-26 | 2025-06 | 001563       | VA         | Loket | Riska   |
| Kristiandi               | B9   | 56  |      300.000 | 2025-06-26 | 2025-06 | 001564       | VA         | Loket | Riska   |
| Yudian Fantoni           | B9   | 36  |      300.000 | 2025-06-26 | 2025-06 | 001565       | Tunai      | Loket | Riska   |
| Handrian Wijaya          | DD1  | 5   |      250.000 | 2025-06-26 | 2025-06 | 001566       | Transfer   | Loket | Riska   |
| Dennie                   | B9   | 29  |      300.000 | 2025-06-26 | 2025-05 | 001567       | Transfer   | Loket | Riska   |
| Liyanawati               | AX7  | 16  |      350.000 | 2025-06-26 | 2025-07 | 001568       | Transfer   | Loket | Riska   |
| Sukardi                  | AX5  | 21  |      250.000 | 2025-06-26 | 2025-05 | 001569       | Transfer   | Loket | Riska   |
| Benny Supardjo           | C6   | 29  |      225.000 | 2025-06-26 | 2025-06 | 001570       | Transfer   | Loket | Riska   |
| Jhoni Candra             | C1   | 6   |      225.000 | 2025-06-26 | 2025-06 | 001571       | Transfer   | Loket | Riska   |
| Teddy                    | AX3  | 53  |      200.000 | 2025-06-26 | 2025-06 | 001572       | Transfer   | Loket | Riska   |
| Riecson Wullur           | JA   | 23  |      300.000 | 2025-06-26 | 2025-06 | 001575       | Transfer   | Loket | Riska   |
| Tham Alifin              | AX5  | 29  |      250.000 | 2025-06-30 | 2025-06 | 001577       | Tunai      | Loket | Riska   |
| Tham Alifin              | AX6  | 6   |      250.000 | 2025-06-30 | 2025-06 | 001578       | Tunai      | Loket | Riska   |
| Yuliana                  | C6   | 31  |      225.000 | 2025-06-30 | 2025-06 | 001579       | Transfer   | Loket | Riska   |
| Rossela                  | C6   | 53  |      225.000 | 2025-06-30 | 2025-05 | 001582       | Transfer   | Loket | Riska   |
| Mohamad Yusuf            | B8   | 81  |      300.000 | 2025-06-30 | 2025-06 | 001584       | Transfer   | Loket | Riska   |
| Badai Rosamudra          | AX5  | 56  |      200.000 | 2025-06-30 | 2025-06 | 001585       | Transfer   | Loket | Riska   |
| Ernest Kurniawan         | DD2  | 8   |      250.000 | 2025-06-30 | 2025-06 | 001586       | Transfer   | Loket | Riska   |
| Ferry Sofianto           | DD2  | 12  |      250.000 | 2025-06-30 | 2025-07 | 001587       | Transfer   | Loket | Riska   |
| Budhi Mulyadi            | DD3  | 3   |      250.000 | 2025-06-30 | 2025-07 | 001588       | Transfer   | Loket | Riska   |
| Stefanus Andre           | AX9  | 50  |      300.000 | 2025-06-30 | 2025-07 | 001590       | Transfer   | Loket | Riska   |
| Carolina                 | C1   | 10  |      225.000 | 2025-06-30 | 2025-06 | 001591       | Transfer   | Loket | Riska   |

## Juli 2025

| Nama                     | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC   | Petugas |
| ------------------------ | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ----- | ------- |
| Shafa Salsabila          | AM2  | 18  |      300.000 | 2025-07-01 | 2025-06 | 001592       | Transfer   | Loket | Riska   |
| Tanu Wijaya              | DD2  | 7   |      250.000 | 2025-07-01 | 2025-07 | 001593       | Transfer   | Loket | Riska   |
| Viyan Andhika            | AX7  | 19  |      350.000 | 2025-07-01 | 2025-07 | 001594       | Transfer   | Loket | Riska   |
| Selvy                    | AX8  | 7   |      300.000 | 2025-07-01 | 2025-07 | 001595       | Transfer   | Loket | Riska   |
| Stefanus                 | C3   | 9   |      225.000 | 2025-07-01 | 2025-06 | 001596       | Transfer   | Loket | Riska   |
| Nisrin                   | B10  | 7   |      300.000 | 2025-07-01 | 2025-05 | 001598       | Transfer   | Loket | Riska   |
| Stephanie Setiawan       | DD1  | 6   |      250.000 | 2025-07-01 | 2025-06 | 001599       | Transfer   | Loket | Riska   |
| Indomaret Grand Duta 2   | E7   | 27  |      300.000 | 2025-07-01 | 2025-06 | 001600       | Tunai      | Loket | Riska   |
| Indomaret Grand Duta 2   | E7   | 28  |      300.000 | 2025-07-01 | 2025-06 | 001601       | Tunai      | Loket | Riska   |
| Indomaret Grand Duta 1   | JA   | 10  |      300.000 | 2025-07-01 | 2025-07 | 001602       | Tunai      | Loket | Riska   |
| Indomaret Grand Duta 1   | JA   | 11  |      300.000 | 2025-07-01 | 2025-07 | 001603       | Tunai      | Loket | Riska   |
| Herman                   | DD3  | 10  |      250.000 | 2025-07-02 | 2025-07 | 001604       | VA         | Loket | Riska   |
| Lidia Apriani            | AX5  | 70  |      200.000 | 2025-07-02 | 2025-07 | 001605       | VA         | Loket | Riska   |
| Leo Baratanata           | DD1  | 15  |      250.000 | 2025-07-02 | 2025-07 | 001606       | VA         | Loket | Riska   |
| Indrawan                 | AX3  | 39  |      200.000 | 2025-07-02 | 2025-07 | 001607       | VA         | Loket | Riska   |
| Melina                   | AX8  | 36  |      300.000 | 2025-07-02 | 2025-07 | 001608       | VA         | Loket | Riska   |
| Drya Nursanti            | AX5  | 72  |      200.000 | 2025-07-02 | 2025-07 | 001610       | VA         | Loket | Riska   |
| Friciel Yael             | B8   | 79  |      300.000 | 2025-07-02 | 2025-07 | 001611       | VA         | Loket | Riska   |
| Silviana Victoria        | DD1  | 16  |      250.000 | 2025-07-02 | 2025-07 | 001612       | VA         | Loket | Riska   |
| Rangga Afyan Dwiokta     | AX6  | 62  |      250.000 | 2025-07-02 | 2025-06 | 001617       | Transfer   | Loket | Riska   |
| Tio Fani                 | AX8  | 6   |      300.000 | 2025-07-02 | 2025-07 | 001618       | Transfer   | Loket | Riska   |
| Luman                    | AX7  | 37  |      350.000 | 2025-07-02 | 2025-07 | 001619       | Transfer   | Loket | Riska   |
| Siska                    | AX5  | 27  |      250.000 | 2025-07-02 | 2025-02 | 001621       | Transfer   | Loket | Riska   |
| Albertus Chandra         | AX8  | 60  |      300.000 | 2025-07-02 | 2025-07 | 001622       | Transfer   | Loket | Riska   |
| Nani Junaeni             | AX8  | 19  |      300.000 | 2025-07-02 | 2025-06 | 001624       | Transfer   | Loket | Riska   |
| Konnie Gunawan           | B9   | 58  |      300.000 | 2025-07-02 | 2025-06 | 001625       | Transfer   | Loket | Riska   |
| Pramadasyah              | B9   | 32  |      300.000 | 2025-07-02 | 2025-06 | 001626       | Transfer   | Loket | Riska   |
| Novriyansyah Karna       | AM1  | 11  |      300.000 | 2025-07-02 | 2025-06 | 001627       | Transfer   | Loket | Riska   |
| Andhi Indarto            | E7   | 12  |      300.000 | 2025-07-02 | 2025-07 | 001629       | Transfer   | Loket | Riska   |
| Lina Trisnawati          | E7   | 26  |      300.000 | 2025-07-02 | 2025-05 | 001630       | Transfer   | Loket | Riska   |
| Nurul Kurnia Wati        | B10  | 33  |      300.000 | 2025-07-02 | 2025-06 | 001631       | Transfer   | Loket | Riska   |
| Christy Veronika         | B10  | 3   |      300.000 | 2025-07-02 | 2025-02 | 001632       | Transfer   | Loket | Riska   |
| Hansen                   | B9   | 7   |      300.000 | 2025-07-02 | 2025-07 | 001633       | Transfer   | Loket | Riska   |
| Yusron Fauzi             | C8   | 12  |      225.000 | 2025-07-02 | 2025-07 | 001634       | Tunai      | Loket | Riska   |
| Multi Infiniti Pro       | JA   | 8   |      300.000 | 2025-07-03 | 2025-07 | 001635       | VA         | Loket | Riska   |
| Bambang Supriyadi        | C7   | 38  |      225.000 | 2025-07-03 | 2025-07 | 001637       | VA         | Loket | Riska   |
| Evi Yulianti             | AX3  | 35  |      200.000 | 2025-07-03 | 2025-07 | 001638       | VA         | Loket | Riska   |
| Giffari Lestyonanda      | B8   | 27  |      300.000 | 2025-07-03 | 2025-07 | 001639       | VA         | Loket | Riska   |
| Lista Akdriaty           | C3   | 7   |      225.000 | 2025-07-03 | 2025-07 | 001640       | VA         | Loket | Riska   |
| Linda Wati               | B10  | 39  |      300.000 | 2025-07-03 | 2025-07 | 001642       | VA         | Loket | Riska   |
| Eriyono Budi             | AX3  | 69  |      200.000 | 2025-07-03 | 2025-07 | 001643       | VA         | Loket | Riska   |
| Chandra                  | AX6  | 19  |      350.000 | 2025-07-03 | 2025-07 | 001644       | VA         | Loket | Riska   |
| Sugandi                  | D8   | 2   |      350.000 | 2025-07-03 | 2025-07 | 001645       | VA         | Loket | Riska   |
| Noer hartati             | C6   | 59  |      225.000 | 2025-07-03 | 2025-07 | 001646       | VA         | Loket | Riska   |
| Julio Andrian            | AX3  | 29  |      200.000 | 2025-07-03 | 2025-07 | 001649       | Transfer   | Loket | Riska   |
| Lilih Yuliani            | AX5  | 36  |      200.000 | 2025-07-03 | 2025-06 | 001651       | Transfer   | Loket | Riska   |
| Evi / GBI                | JA   | 1   |      300.000 | 2025-07-03 | 2025-07 | 001652       | Transfer   | Loket | Riska   |
| Tri Septiyanti           | E7   | 7   |      300.000 | 2025-07-03 | 2025-06 | 001653       | Transfer   | Loket | Riska   |
| Rizky Arif               | AX8  | 5   |      300.000 | 2025-07-03 | 2025-06 | 001654       | Tunai      | Loket | Riska   |
| Vina Karya               | AL2  | 11  |      300.000 | 2025-07-04 | 2025-06 | 001655       | Tunai      | Loket | Riska   |
| Kartika Puspa            | AX7  | 8   |      350.000 | 2025-07-04 | 2025-07 | 001656       | VA         | Loket | Riska   |
| Rita                     | B10  | 21  |      300.000 | 2025-07-04 | 2025-07 | 001657       | VA         | Loket | Riska   |
| Mohammad Syukron         | C6   | 19  |      225.000 | 2025-07-04 | 2025-07 | 001658       | VA         | Loket | Riska   |
| Felix Hadianto           | AX5  | 76  |      200.000 | 2025-07-04 | 2025-07 | 001660       | VA         | Loket | Riska   |
| Hanindya Puspita         | JA   | 5   |      300.000 | 2025-07-04 | 2025-07 | 001661       | VA         | Loket | Riska   |
| Noviana                  | AX7  | 33  |      350.000 | 2025-07-04 | 2025-07 | 001662       | VA         | Loket | Riska   |
| Ediana Sigalingging      | AM2  | 1   |      300.000 | 2025-07-04 | 2025-06 | 001664       | Tunai      | Loket | Riska   |
| Ediana Sigalingging      | AM2  | 2   |      300.000 | 2025-07-04 | 2025-06 | 001665       | Tunai      | Loket | Riska   |
| Anugerah Primarasa       | DD3  | 6   |      250.000 | 2025-07-04 | 2025-07 | 001666       | Transfer   | Loket | Riska   |
| M Alfian Khairi          | C9   | 7   |      225.000 | 2025-07-04 | 2025-07 | 001668       | Transfer   | Loket | Riska   |
| Wirya Dharmawan          | AX3  | 71  |      200.000 | 2025-07-04 | 2025-07 | 001669       | Transfer   | Loket | Riska   |
| Sinta Dewi               | AX5  | 33  |      250.000 | 2025-07-04 | 2025-06 | 001670       | Transfer   | Loket | Riska   |
| Siti Suprapti            | AX2  | 8&9 |      500.000 | 2025-07-04 | 2025-07 | 001671       | Transfer   | Loket | Riska   |
| Hans Jakal Perdana       | C1   | 12  |      225.000 | 2025-07-04 | 2025-07 | 001674       | Tunai      | Loket | Riska   |
| ME diah Ayu              | B10  | 27  |      300.000 | 2025-07-04 | 2025-06 | 001675       | Transfer   | Loket | Riska   |
| Tain Yunan               | AM1  | 2   |      300.000 | 2025-07-07 | 2025-07 | 001677       | VA         | Loket | Riska   |
| Dedi Sugiarto            | DD1  | 19  |      250.000 | 2025-07-07 | 2025-07 | 001678       | VA         | Loket | Riska   |
| Rika                     | AM2  | 10  |      300.000 | 2025-07-07 | 2025-07 | 001679       | VA         | Loket | Riska   |
| Noviani Harianto         | C11  | 10  |      225.000 | 2025-07-07 | 2025-07 | 001680       | VA         | Loket | Riska   |
| Regina Adytia            | AX8  | 3   |      300.000 | 2025-07-07 | 2025-07 | 001681       | VA         | Loket | Riska   |
| Elroys Oktavianus        | B10  | 63  |      300.000 | 2025-07-07 | 2025-07 | 001682       | VA         | Loket | Riska   |
| Rike Saxena              | C9   | 5   |      225.000 | 2025-07-07 | 2025-07 | 001685       | VA         | Loket | Riska   |
| Jangkau Lintas Nusantara | E7   | 50  |      300.000 | 2025-07-07 | 2025-07 | 001686       | Transfer   | Loket | Riska   |
| Gusnita                  | B9   | 35  |      300.000 | 2025-07-07 | 2025-07 | 001688       | Transfer   | Loket | Riska   |
| Friska                   | AX5  | 57  |      250.000 | 2025-07-07 | 2025-07 | 001689       | Tunai      | Loket | Riska   |
| Andri Mustakim           | AX5  | 5   |      250.000 | 2025-07-07 | 2025-07 | 001693       | Transfer   | Loket | Riska   |
| Budiman                  | C6   | 61  |      225.000 | 2025-07-08 | 2024-11 | 001695       | Tunai      | Loket | Indra   |
| wulan januri / Ali       | C6   | 37  |      225.000 | 2025-07-08 | 2025-07 | 001696       | Transfer   | Loket | Ari     |
| Lisa Loandha             | C11  | 20  |      225.000 | 2025-07-08 | 2025-07 | 001697       | Transfer   | Loket | Ari     |
| Sapto Rini               | AX6  | 7   |      350.000 | 2025-07-08 | 2025-07 | 001699       | Transfer   | Loket | Ari     |
| Putri Alvina             | AX7  | 27  |      350.000 | 2025-07-08 | 2025-07 | 001700       | Transfer   | Loket | Ari     |
| Putri Alvina             | JA   | 18  |      300.000 | 2025-07-08 | 2025-07 | 001701       | Transfer   | Loket | Ari     |
| Mohamad Arif             | E7   | 18  |      300.000 | 2025-07-08 | 2025-07 | 001703       | VA         | Loket | Ari     |
| Deri Yatno               | AX7  | 29  |      350.000 | 2025-07-08 | 2025-07 | 001705       | VA         | Loket | Ari     |
| Charlie Chandra          | AX5  | 38  |      200.000 | 2025-07-08 | 2025-07 | 001706       | VA         | Loket | Ari     |
| Devi Febrian             | DD1  | 18  |      250.000 | 2025-07-08 | 2025-07 | 001707       | VA         | Loket | Ari     |
| Nicholas Widjaja         | DD1  | 7   |      250.000 | 2025-07-08 | 2025-07 | 001708       | VA         | Loket | Ari     |
| Anista Retno             | JA   | 16  |      300.000 | 2025-07-08 | 2025-06 | 001709       | Transfer   | Loket | Ari     |
| Mohammad Ammar Kurnia    | B9   | 18  |      300.000 | 2025-07-08 | 2025-06 | 001710       | Transfer   | Loket | Ari     |
| Thio Werry               | C7   | 26  |      225.000 | 2025-07-08 | 2025-06 | 001711       | Transfer   | Loket | Ari     |
| Gani Elfana              | AX7  | 21  |      350.000 | 2025-07-08 | 2025-07 | 001712       | Transfer   | Loket | Ari     |
| Gani Elfana              | AM1  | 10  |      300.000 | 2025-07-08 | 2025-07 | 001713       | Transfer   | Loket | Ari     |
| Henni Johanes            | AX5  | 59  |      250.000 | 2025-07-08 | 2025-07 | 001714       | Transfer   | Loket | Ari     |
| Anter Aja                | JA   | 15  |      300.000 | 2025-07-08 | 2025-07 | 001715       | Tunai      | Loket | Ari     |
| Kuswanto                 | AM2  | 19  |      300.000 | 2025-07-09 | 2025-07 | 001716       | VA         | Loket | Riska   |
| Hon King Wie             | C2   | 20  |      225.000 | 2025-07-09 | 2025-06 | 001719       | Transfer   | Loket | Riska   |
| Purnawati SE             | AX6  | 9   |      350.000 | 2025-07-10 | 2025-06 | 001720       | Tunai      | Loket | Riska   |
| Bargani                  | C10  | 6   |      225.000 | 2025-07-10 | 2025-07 | 001721       | Tunai      | Loket | Riska   |
| Nurhapsah                | AM1  | 5   |      300.000 | 2025-07-10 | 2025-07 | 001722       | VA         | Loket | Riska   |
| Ervina                   | AM1  | 3   |      300.000 | 2025-07-10 | 2025-07 | 001723       | VA         | Loket | Riska   |
| Else                     | DD1  | 2   |      250.000 | 2025-07-10 | 2025-07 | 001724       | VA         | Loket | Riska   |
| Stanley Giovanni         | AX5  | 23  |      250.000 | 2025-07-10 | 2025-07 | 001725       | Transfer   | Loket | Riska   |
| Nova Diana               | B10  | 6   |      300.000 | 2025-07-10 | 2025-07 | 001726       | Transfer   | Loket | Riska   |
| Abdul Azis Saifuddin     | C11  | 8   |      225.000 | 2025-07-10 | 2025-07 | 001727       | Transfer   | Loket | Riska   |
| Yuki Erik                | B10  | 8   |      300.000 | 2025-07-11 | 2025-07 | 001728       | VA         | Loket | Riska   |
| Agnes Wiwik              | B10  | 77  |      300.000 | 2025-07-11 | 2025-07 | 001729       | VA         | Loket | Riska   |
| Arnold Dwi Putra         | AX5  | 26  |      200.000 | 2025-07-11 | 2025-07 | 001730       | VA         | Loket | Riska   |
| Anastasia Dame           | AX7  | 7   |      350.000 | 2025-07-11 | 2025-07 | 001731       | VA         | Loket | Riska   |
| Yenni                    | C9   | 6   |      225.000 | 2025-07-11 | 2025-07 | 001732       | VA         | Loket | Riska   |
| Muhammad Fachdilah       | AX6  | 70  |      250.000 | 2025-07-11 | 2025-07 | 001733       | Transfer   | Loket | Riska   |
| Yani Lestari             | AX7  | 6   |      350.000 | 2025-07-14 | 2025-07 | 001735       | VA         | Loket | Riska   |
| Seftiningtyas            | AX3  | 17  |      200.000 | 2025-07-14 | 2025-07 | 001736       | VA         | Loket | Riska   |
| Imas Rahayu Ningsih      | AX8  | 25  |      300.000 | 2025-07-14 | 2025-07 | 001737       | VA         | Loket | Riska   |
| Kajohan                  | AX5  | 80  |      200.000 | 2025-07-14 | 2025-07 | 001738       | VA         | Loket | Riska   |
| Dila Ardila              | B10  | 25  |      300.000 | 2025-07-14 | 2025-07 | 001739       | VA         | Loket | Riska   |
| Herson                   | D8   | 7   |      350.000 | 2025-07-14 | 2025-07 | 001740       | Transfer   | Loket | Riska   |
| Herson                   | D8   | 3   |      350.000 | 2025-07-14 | 2025-07 | 001741       | Transfer   | Loket | Riska   |
| Herson                   | AX9  | 38  |      300.000 | 2025-07-14 | 2025-06 | 001742       | Transfer   | Loket | Riska   |
| Herson                   | DD2  | 19  |      250.000 | 2025-07-14 | 2025-06 | 001743       | Transfer   | Loket | Riska   |
| Jaya Iskandar            | C9   | 8   |      225.000 | 2025-07-14 | 2025-06 | 001744       | Tunai      | Loket | Riska   |
| Herson                   | C6   | 55  |      225.000 | 2025-07-14 | 2025-06 | 001745       | Transfer   | Loket | Riska   |
| Herson                   | C7   | 28  |      225.000 | 2025-07-14 | 2025-07 | 001746       | Transfer   | Loket | Riska   |
| Sonya Elia Chandra       | AX5  | 20  |      200.000 | 2025-07-14 | 2025-07 | 001748       | Transfer   | Loket | Riska   |
| Johanna Hermawan         | DD2  | 3   |      250.000 | 2025-07-14 | 2025-07 | 001749       | Transfer   | Loket | Riska   |
| GSJA Abbacare            | AL2  | 12  |      300.000 | 2025-07-14 | 2025-07 | 001752       | Transfer   | Loket | Riska   |
| Sabeth                   | AX8  | 30  |      300.000 | 2025-07-15 | 2025-07 | 001754       | VA         | Loket | Riska   |
| Nico Cristian            | E7   | 8   |      300.000 | 2025-07-15 | 2025-07 | 001755       | VA         | Loket | Riska   |
| Jhon Delon               | AX5  | 78  |      200.000 | 2025-07-15 | 2025-07 | 001756       | Tunai      | Loket | Riska   |
| Teguh Santoso            | C3   | 3   |      225.000 | 2025-07-15 | 2025-07 | 001757       | VA         | Loket | Riska   |
| Rizki Andriani           | B9   | 6   |      300.000 | 2025-07-15 | 2024-12 | 001758       | Transfer   | Loket | Riska   |
| Marliana Sari            | AX5  | 6   |      200.000 | 2025-07-15 | 2025-07 | 001759       | Tunai      | Loket | Riska   |
| Verawati Wijaya          | AX5  | 18  |      200.000 | 2025-07-16 | 2025-07 | 001762       | VA         | Loket | Riska   |
| Lusia Tungadi            | D8   | 11  |      350.000 | 2025-07-16 | 2025-07 | 001766       | Transfer   | Loket | Riska   |
| Betty Heriasati          | AX5  | 63  |      250.000 | 2025-07-17 | 2025-04 | 001771       | Transfer   | Loket | Riska   |
| Kristianto               | JA   | 2   |      300.000 | 2025-07-18 | 2025-07 | 001773       | VA         | Loket | Riska   |
| Yulin                    | AX8  | 39  |      300.000 | 2025-07-18 | 2025-07 | 001774       | VA         | Loket | Riska   |
| Rahdian Hafiz            | DD2  | 17  |      250.000 | 2025-07-18 | 2025-06 | 001775       | VA         | Loket | Riska   |
| Insan Gunandar           | C9   | 10  |      225.000 | 2025-07-21 | 2025-07 | 001778       | Tunai      | Loket | Riska   |
| Herlisa Resti            | B8   | 50  |      300.000 | 2025-07-21 | 2025-06 | 001779       | VA         | Loket | Riska   |
| Herwati                  | AM1  | 8   |      300.000 | 2025-07-21 | 2025-07 | 001781       | Tunai      | Loket | Riska   |
| Fransisca Herlin         | B8   | 35  |      300.000 | 2025-07-21 | 2025-07 | 001782       | VA         | Loket | Riska   |
| Arlia Anwar              | B10  | 78  |      300.000 | 2025-07-21 | 2025-07 | 001783       | VA         | Loket | Riska   |
| Endah Siwi               | C5   | 10  |      225.000 | 2025-07-21 | 2025-07 | 001784       | VA         | Loket | Riska   |
| Ali Ardianto             | DD1  | 8   |      250.000 | 2025-07-21 | 2024-11 | 001785       | Transfer   | Loket | Riska   |
| Etri Septivia            | B9   | 59  |      300.000 | 2025-07-21 | 2025-05 | 001786       | Transfer   | Loket | Riska   |
| Abigail Nila             | AX8  | 53  |      300.000 | 2025-07-21 | 2025-07 | 001788       | Transfer   | Loket | Riska   |
| Dendy                    | AX3  | 91  |      200.000 | 2025-07-21 | 2025-07 | 001789       | Transfer   | Loket | Riska   |
| Siti Nurwati             | AX6  | 17  |      350.000 | 2025-07-22 | 2025-07 | 001791       | VA         | Loket | Riska   |
| Hadianto                 | C8   | 11  |      225.000 | 2025-07-23 | 2025-07 | 001794       | VA         | Loket | Riska   |
| Riyandi Susanto          | C11  | 28  |      225.000 | 2025-07-23 | 2025-07 | 001795       | Transfer   | Loket | Riska   |
| Hartinna                 | B9   | 25  |      300.000 | 2025-07-23 | 2025-05 | 001796       | Transfer   | Loket | Riska   |
| Ajeng                    | DD1  | 17  |      250.000 | 2025-07-24 | 2025-07 | 001798       | VA         | Loket | Riska   |
| Hendry                   | DD1  | 9   |      250.000 | 2025-07-24 | 2025-07 | 001799       | VA         | Loket | Riska   |
| Rohani Susi Herlin       | B9   | 10  |      300.000 | 2025-07-24 | 2025-07 | 001800       | Transfer   | Loket | Riska   |
| Siti Fatonah             | AX5  | 32  |      200.000 | 2025-07-25 | 2025-07 | 001803       | Tunai      | Loket | Riska   |
| Edy Hartono              | JA   | 19  |      300.000 | 2025-07-25 | 2025-07 | 001804       | VA         | Loket | Riska   |
| Konnie Gunawan           | B9   | 58  |      300.000 | 2025-07-25 | 2025-07 | 001805       | Transfer   | Loket | Riska   |
| Henry Tulus              | DD2  | 5   |      250.000 | 2025-07-25 | 2025-07 | 001808       | Transfer   | Loket | Riska   |
| Andrew                   | C8   | 3   |      225.000 | 2025-07-25 | 2025-06 | 001809       | Transfer   | Loket | Riska   |
| Ikhwan Rosyiidian        | AX8  | 52  |      300.000 | 2025-07-25 | 2025-07 | 001811       | Transfer   | Loket | Riska   |
| Handrian Wijaya          | DD1  | 5   |      250.000 | 2025-07-25 | 2025-07 | 001812       | Transfer   | Loket | Riska   |
| Karim Amirullah          | B10  | 16  |      300.000 | 2025-07-28 | 2025-07 | 001814       | VA         | Loket | Riska   |
| Ronald Yahya             | C1   | 2   |      225.000 | 2025-07-28 | 2025-07 | 001815       | VA         | Loket | Riska   |
| Nesa Apriari             | B8   | 67  |      300.000 | 2025-07-28 | 2025-07 | 001816       | VA         | Loket | Riska   |
| Kristiandi               | B9   | 56  |      300.000 | 2025-07-28 | 2025-07 | 001817       | VA         | Loket | Riska   |
| Yuliana                  | C6   | 31  |      225.000 | 2025-07-28 | 2025-07 | 001818       | Transfer   | Loket | Riska   |
| Denis Sofia Lestari      | AX3  | 55  |      200.000 | 2025-07-28 | 2025-07 | 001820       | Transfer   | Loket | Riska   |
| Jhoni Candra             | C1   | 6   |      225.000 | 2025-07-28 | 2025-07 | 001821       | Transfer   | Loket | Riska   |
| Badai Rosamudra          | AX5  | 56  |      200.000 | 2025-07-28 | 2025-07 | 001822       | Transfer   | Loket | Riska   |
| Mohamad Yusuf            | B8   | 81  |      300.000 | 2025-07-28 | 2025-07 | 001824       | Transfer   | Loket | Riska   |
| ME Diah Ayu              | B10  | 27  |      300.000 | 2025-07-28 | 2025-07 | 001825       | Transfer   | Loket | Riska   |
| Stephanie Setiawan       | DD1  | 6   |      250.000 | 2025-07-28 | 2025-07 | 001828       | Transfer   | Loket | Riska   |
| Elly Swanti              | C1   | 11  |      225.000 | 2025-07-28 | 2025-07 | 001829       | Transfer   | Loket | Riska   |
| Sinar Winarni            | AX7  | 5   |      350.000 | 2025-07-28 | 2025-07 | 001830       | Transfer   | Loket | Riska   |
| Shafa Salsabila          | AM2  | 18  |      300.000 | 2025-07-28 | 2025-07 | 001831       | Transfer   | Loket | Riska   |
| Sahrial Lubis            | C2   | 27  |      225.000 | 2025-07-29 | 2025-07 | 001833       | Tunai      | Loket | Riska   |
| Yudian Fantoni           | B9   | 36  |      300.000 | 2025-07-29 | 2025-07 | 001834       | Tunai      | Loket | Ickna   |
| Reza Satria              | AX6  | 66  |      250.000 | 2025-07-29 | 2025-07 | 001836       | Transfer   | Loket | Riska   |
| Ernest Kurniawan         | DD2  | 8   |      250.000 | 2025-07-29 | 2025-07 | 001839       | Transfer   | Loket | Riska   |
| Dessy                    | AX7  | 55  |      350.000 | 2025-07-29 | 2025-07 | 001840       | Transfer   | Loket | Riska   |
| Benny Supardjo           | C6   | 29  |      225.000 | 2025-07-30 | 2025-07 | 001843       | Transfer   | Loket | Riska   |
| Dennie                   | B9   | 29  |      300.000 | 2025-07-30 | 2025-06 | 001844       | Transfer   | Loket | Riska   |
| Pramadasyah              | B9   | 32  |      300.000 | 2025-07-31 | 2025-07 | 001846       | Transfer   | Loket | Riska   |
| Riko                     | AX3  | 11  |      200.000 | 2025-07-31 | 2025-07 | 001848       | Transfer   | Loket | Riska   |
| Nurul Kurnia Wati        | B10  | 33  |      300.000 | 2025-07-31 | 2025-07 | 001850       | Transfer   | Loket | Riska   |
| Ediana Sigalingging      | AM2  | 1   |      300.000 | 2025-07-31 | 2025-07 | 001852       | Tunai      | Loket | Riska   |
| Ediana Sigalingging      | AM2  | 2   |      300.000 | 2025-07-31 | 2025-07 | 001853       | Tunai      | Loket | Riska   |
| Carmel Anugerah          | E7   | 29  |      300.000 | 2025-07-31 | 2025-07 | 001854       | Transfer   | Loket | Riska   |

## Agustus 2025

| Nama                   | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC   | Petugas |
| ---------------------- | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ----- | ------- |
| Purnawati SE           | AX6  | 9   |      350.000 | 2025-08-01 | 2025-07 | 001856       | Tunai      | Loket | Riska   |
| Linda                  | B9   | 79  |      300.000 | 2025-08-01 | 2025-05 | 001858       | Transfer   | Loket | Riska   |
| Rangga Afyan Dwiokta   | AX6  | 62  |      250.000 | 2025-08-04 | 2025-07 | 001892       | Transfer   | Loket | Riska   |
| Rossela                | C6   | 53  |      225.000 | 2025-08-04 | 2025-06 | 001901       | Transfer   | Loket | Riska   |
| Bayu Septian           | B9   | 81  |      300.000 | 2025-08-04 | 2024-01 | 001906       | Transfer   | Loket | Riska   |
| Lilih Yuliani          | AX5  | 36  |      200.000 | 2025-08-04 | 2025-07 | 001908       | Transfer   | Loket | Riska   |
| Sinta Dewi             | AX5  | 33  |      250.000 | 2025-08-04 | 2025-07 | 001909       | Transfer   | Loket | Riska   |
| Vina Karya             | AL2  | 11  |      300.000 | 2025-08-04 | 2025-07 | 001911       | Tunai      | Loket | Riska   |
| Indomaret Grand Duta 2 | E7   | 27  |      300.000 | 2025-08-04 | 2025-07 | 001912       | Tunai      | Loket | Riska   |
| Indomaret Grand Duta 2 | E7   | 28  |      300.000 | 2025-08-04 | 2025-07 | 001913       | Tunai      | Loket | Riska   |
| Rieckson Wulur         | JA   | 23  |      300.000 | 2025-08-05 | 2025-07 | 001936       | Transfer   | Loket | Riska   |
| Stefanus               | C3   | 9   |      225.000 | 2025-08-06 | 2025-07 | 001955       | Transfer   | Loket | Riska   |
| Anista Retno           | JA   | 16  |      300.000 | 2025-08-06 | 2025-07 | 001956       | Transfer   | Loket | Riska   |
| Novriyansyah Karna     | AM1  | 11  |      300.000 | 2025-08-07 | 2025-07 | 001975       | Transfer   | Loket | Riska   |
| Jaya Iskandar          | C9   | 8   |      225.000 | 2025-08-08 | 2025-07 | 001985       | Tunai      | Loket | Riska   |
| Herson                 | AX9  | 38  |      300.000 | 2025-08-11 | 2025-07 | 002010       | Transfer   | Loket | Riska   |
| Herson                 | C6   | 55  |      225.000 | 2025-08-11 | 2025-07 | 002011       | Transfer   | Loket | Riska   |
| Herson                 | AL2  | 5   |      300.000 | 2025-08-11 | 2025-06 | 002013       | Transfer   | Loket | Riska   |
| Herson                 | AL2  | 5   |      300.000 | 2025-08-11 | 2025-07 | 002014       | Transfer   | Loket | Riska   |
| Hary                   | C9   | 11  |      225.000 | 2025-08-11 | 2025-01 | 002015       | Transfer   | Loket | Riska   |
| Etri Septivia          | B9   | 59  |      300.000 | 2025-08-11 | 2025-06 | 002016       | Transfer   | Loket | Riska   |
| Rizky Arif             | AX8  | 5   |      300.000 | 2025-08-11 | 2025-07 | 002017       | Tunai      | Loket | Riska   |
| Mohammad Ammar         | B9   | 18  |      300.000 | 2025-08-13 | 2025-07 | 002029       | Tunai      | Loket | Riska   |
| Susanti                | E7   | 32  |      300.000 | 2025-08-15 | 2025-06 | 002042       | Transfer   | Loket | Riska   |
| Susanti                | E7   | 33  |      300.000 | 2025-08-15 | 2025-06 | 002043       | Transfer   | Loket | Riska   |
| Budiman                | C6   | 61  |      225.000 | 2025-08-21 | 2024-12 | 002065       | Tunai      | Loket | Riska   |
| Andrew                 | C8   | 3   |      225.000 | 2025-08-25 | 2025-07 | 002082       | Transfer   | Loket | Riska   |
| Meti Oktavia           | DD2  | 2   |      250.000 | 2025-08-25 | 2025-09 | 002084       | Transfer   | Loket | Riska   |
| Liyanawati             | AX7  | 16  |      350.000 | 2025-08-25 | 2025-09 | 002086       | Transfer   | Loket | Riska   |
| Dennie                 | B9   | 29  |      300.000 | 2025-08-26 | 2025-07 | 002092       | Transfer   | Loket | Rsika   |
| Wulan Januri           | C6   | 37  |      225.000 | 2025-08-26 | 2025-09 | 002093       | Transfer   | Loket | Rsika   |
| Stefanus Andre         | AX9  | 50  |      300.000 | 2025-08-29 | 2025-09 | 002106       | Transfer   | Loket | Riska   |
| Lukmanul Hakim         | C8   | 9   |      225.000 | 2025-08-29 | 2025-05 | 002107       | Transfer   | Loket | Riska   |
| Ferry Sofianto         | DD2  | 12  |      250.000 | 2025-08-29 | 2025-09 | 002109       | Transfer   | Loket | Riska   |
| Budhi Mulyadi          | DD3  | 3   |      250.000 | 2025-08-29 | 2025-09 | 002111       | Transfer   | Loket | Riska   |

## September 2025

| Nama                         | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC   | Petugas |
| ---------------------------- | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ----- | ------- |
| Yusron Fauzi                 | C8   | 12  |      225.000 | 2025-09-01 | 2025-09 | 002116       | Tunai      | Loket | Riska   |
| Sukardi                      | AX5  | 21  |      250.000 | 2025-09-01 | 2025-06 | 002123       | Transfer   | Loket | Riska   |
| Tanu Wijaya                  | DD2  | 7   |      250.000 | 2025-09-01 | 2025-09 | 002124       | Transfer   | Loket | Riska   |
| Julio Andrian                | AX3  | 29  |      200.000 | 2025-09-01 | 2025-09 | 002126       | Transfer   | Loket | Riska   |
| Siti Suprapti                | AX2  | 8&9 |      500.000 | 2025-09-01 | 2025-09 | 002127       | Transfer   | Loket | Riska   |
| Idrus                        | E7   | 10  |      300.000 | 2025-09-01 | 2025-07 | 002128       | Transfer   | Loket | Riska   |
| Rezky Iriansyah Anugrah      | B9   | 78  |      300.000 | 2025-09-01 | 2025-09 | 002133       | Transfer   | Loket | Riska   |
| Viyan Andhika                | AX7  | 19  |      350.000 | 2025-09-01 | 2025-09 | 002135       | Transfer   | Loket | Riska   |
| Tio Fani                     | AX8  | 6   |      300.000 | 2025-09-01 | 2025-09 | 002136       | Transfer   | Loket | Riska   |
| Anugerah Primarasa           | DD3  | 6   |      250.000 | 2025-09-01 | 2025-09 | 002137       | Transfer   | Loket | Riska   |
| Meliana                      | C7   | 58  |      225.000 | 2025-09-01 | 2025-09 | 002138       | Transfer   | Loket | Riska   |
| Selvy                        | AX8  | 7   |      300.000 | 2025-09-01 | 2025-09 | 002139       | Transfer   | Loket | Riska   |
| Gani Elfana                  | AX7  | 21  |      350.000 | 2025-09-02 | 2025-09 | 002142       | Transfer   | Loket | Riska   |
| Gani Elfana                  | AM1  | 10  |      300.000 | 2025-09-02 | 2025-09 | 002143       | Transfer   | Loket | Riska   |
| Albertus Chandra             | AX8  | 60  |      300.000 | 2025-09-02 | 2025-09 | 002144       | Transfer   | Loket | Riska   |
| Drya Nursanti                | AX5  | 72  |      200.000 | 2025-09-02 | 2025-09 | 002145       | Transfer   | Loket | Riska   |
| Chandra                      | AX6  | 19  |      350.000 | 2025-09-02 | 2025-09 | 002147       | Transfer   | Loket | Riska   |
| Rossela                      | C6   | 53  |      225.000 | 2025-09-02 | 2025-07 | 002148       | Transfer   | Loket | Riska   |
| Evi Yulianti                 | AX3  | 35  |      200.000 | 2025-09-02 | 2025-09 | 002149       | Transfer   | Loket | Riska   |
| Tri Septiyanti               | E7   | 7   |      300.000 | 2025-09-02 | 2025-07 | 002150       | Transfer   | Loket | Riska   |
| Stanley / Dompet anak Bangsa | AX5  | 23  |      250.000 | 2025-09-02 | 2025-09 | 002155       | Transfer   | Loket | Riska   |
| Siat Suk Jin                 | C5   | 5   |      225.000 | 2025-09-02 | 2025-07 | 002156       | Tunai      | Loket | Riska   |
| Dedi Sugiarto                | DD1  | 19  |      250.000 | 2025-09-02 | 2025-09 | 002157       | Transfer   | Loket | Riska   |
| Indomaret Grand Duta 1       | JA   | 10  |      300.000 | 2025-09-02 | 2025-09 | 002159       | Tunai      | Loket | Riska   |
| Indomaret Grand Duta 1       | JA   | 11  |      300.000 | 2025-09-02 | 2025-09 | 002160       | Tunai      | Loket | Riska   |
| Bambang Supriyadi            | C7   | 38  |      225.000 | 2025-09-03 | 2025-09 | 002162       | Transfer   | Loket | Riska   |
| Hon King Wie                 | C2   | 20  |      225.000 | 2025-09-03 | 2025-07 | 002163       | Transfer   | Loket | Riska   |
| Andhi Indarto                | E7   | 12  |      300.000 | 2025-09-03 | 2025-09 | 002166       | Transfer   | Loket | Riska   |
| Hanafi                       | C7   | 26  |      225.000 | 2025-09-03 | 2025-09 | 002167       | Transfer   | Loket | Riska   |
| Wirya Dharmawan              | AX3  | 71  |      200.000 | 2025-09-03 | 2025-09 | 002168       | Transfer   | Loket | Riska   |
| Ervina                       | AM1  | 5   |      300.000 | 2025-09-04 | 2025-09 | 002170       | VA         | Loket | Riska   |
| Ervina                       | AM1  | 3   |      300.000 | 2025-09-04 | 2025-09 | 002171       | VA         | Loket | Riska   |
| Herman                       | DD3  | 10  |      250.000 | 2025-09-04 | 2025-09 | 002172       | VA         | Loket | Riska   |
| Herlisa Resti                | B8   | 50  |      300.000 | 2025-09-04 | 2025-09 | 002174       | VA         | Loket | Riska   |
| Tain Yunan                   | AM1  | 2   |      300.000 | 2025-09-04 | 2025-09 | 002175       | VA         | Loket | Riska   |
| Deri Yatno                   | AX7  | 29  |      350.000 | 2025-09-04 | 2025-09 | 002176       | VA         | Loket | Riska   |
| Giffari Lestiyonanda         | B8   | 27  |      300.000 | 2025-09-04 | 2025-09 | 002178       | VA         | Loket | Riska   |
| Kartika Puspa                | AX7  | 8   |      350.000 | 2025-09-04 | 2025-09 | 002179       | VA         | Loket | Riska   |
| Felix Hadianto               | AX5  | 76  |      200.000 | 2025-09-04 | 2025-09 | 002180       | VA         | Loket | Riska   |
| Silviana Victoria            | DD1  | 16  |      250.000 | 2025-09-04 | 2025-09 | 002181       | VA         | Loket | Riska   |
| Anita Carolina               | B10  | 32  |      300.000 | 2025-09-04 | 2025-09 | 002182       | VA         | Loket | Riska   |
| Noviani Harianto             | C11  | 10  |      225.000 | 2025-09-04 | 2025-09 | 002183       | VA         | Loket | Riska   |
| Devi Febrian                 | DD1  | 18  |      250.000 | 2025-09-04 | 2025-09 | 002184       | VA         | Loket | Riska   |
| Rita                         | B10  | 21  |      300.000 | 2025-09-04 | 2025-09 | 002185       | VA         | Loket | Riska   |
| Joni                         | AX7  | 23  |      350.000 | 2025-09-04 | 2025-09 | 002189       | VA         | Loket | Riska   |
| Novita Martaningsih          | AX5  | 31  |      250.000 | 2025-09-04 | 2025-09 | 002192       | Tunai      | Loket | Riska   |
| Muhammad Fachdilah           | AX6  | 70  |      250.000 | 2025-09-04 | 2025-09 | 002193       | Transfer   | Loket | Riska   |
| Than Alifin                  | AX5  | 29  |      250.000 | 2025-09-04 | 2025-09 | 002194       | Transfer   | Loket | Riska   |
| Than Alifin                  | AX6  | 6   |      250.000 | 2025-09-04 | 2025-09 | 002195       | Transfer   | Loket | Riska   |
| Dendy                        | AX3  | 91  |      200.000 | 2025-09-04 | 2025-09 | 002196       | Transfer   | Loket | Riska   |
| Andrian Mustakim             | AX5  | 5   |      250.000 | 2025-09-04 | 2025-09 | 002197       | Transfer   | Loket | Riska   |
| Evi / GBI                    | JA   | 1   |      300.000 | 2025-09-04 | 2025-09 | 002198       | Transfer   | Loket | Riska   |
| Sapto Rini Rushapsari        | AX6  | 7   |      350.000 | 2025-09-04 | 2025-09 | 002199       | Transfer   | Loket | Riska   |
| Lisa Loandha                 | C11  | 20  |      225.000 | 2025-09-04 | 2025-09 | 002200       | Transfer   | Loket | Riska   |
| Hartinna                     | B9   | 25  |      300.000 | 2025-09-04 | 2025-06 | 002201       | Transfer   | Loket | Riska   |
| Linda Wati                   | B10  | 39  |      300.000 | 2025-09-08 | 2025-09 | 002206       | VA         | Loket | Riska   |
| Lista Akdriaty               | C3   | 7   |      225.000 | 2025-09-08 | 2025-09 | 002207       | VA         | Loket | Riska   |
| Irwan                        | AX3  | 37  |      200.000 | 2025-09-08 | 2025-09 | 002208       | VA         | Loket | Riska   |
| Regina Adytia                | AX8  | 3   |      300.000 | 2025-09-08 | 2025-09 | 002210       | VA         | Loket | Riska   |
| Imas Rahayu Ningsih          | AX8  | 25  |      300.000 | 2025-09-08 | 2025-09 | 002211       | VA         | Loket | Riska   |
| Rike Saxena                  | C9   | 5   |      225.000 | 2025-09-08 | 2025-09 | 002212       | VA         | Loket | Riska   |
| Rully Angga                  | AX7  | 12  |      350.000 | 2025-09-08 | 2025-09 | 002214       | VA         | Loket | Riska   |
| Sabeth                       | AX8  | 30  |      300.000 | 2025-09-08 | 2025-09 | 002215       | VA         | Loket | Riska   |
| Hanindya Puspita             | JA   | 5   |      300.000 | 2025-09-08 | 2025-09 | 002216       | VA         | Loket | Riska   |
| Dessy                        | AX7  | 55  |      350.000 | 2025-09-08 | 2025-09 | 002217       | VA         | Loket | Riska   |
| Mohamad Arif                 | E7   | 18  |      300.000 | 2025-09-08 | 2025-09 | 002218       | VA         | Loket | Riska   |
| Friciel Yael                 | B8   | 79  |      300.000 | 2025-09-08 | 2025-09 | 002219       | VA         | Loket | Riska   |
| Rika                         | AM2  | 10  |      300.000 | 2025-09-08 | 2025-09 | 002220       | VA         | Loket | Riska   |
| Noviana                      | AX7  | 33  |      350.000 | 2025-09-08 | 2025-09 | 002221       | VA         | Loket | Riska   |
| Lidia Apriani                | AX5  | 70  |      200.000 | 2025-09-08 | 2025-09 | 002222       | VA         | Loket | Riska   |
| Jangkau Lintas Nusantara     | E7   | 50  |      300.000 | 2025-09-08 | 2025-09 | 002223       | Transfer   | Loket | Riska   |
| Luman                        | AX7  | 37  |      350.000 | 2025-09-08 | 2025-09 | 002224       | Transfer   | Loket | Riska   |
| GSJA Abbacare                | AL2  | 12  |      300.000 | 2025-09-08 | 2025-09 | 002226       | Transfer   | Loket | Riska   |
| Abigail Nila                 | AX8  | 53  |      300.000 | 2025-09-08 | 2025-09 | 002228       | Transfer   | Loket | Riska   |
| Jonathan Surya               | B9   | 79  |      300.000 | 2025-09-08 | 2025-06 | 002229       | Transfer   | Loket | Riska   |
| Henni Johanes                | AX5  | 59  |      250.000 | 2025-09-08 | 2025-09 | 002230       | Transfer   | Loket | Riska   |
| Nova Diana                   | B10  | 6   |      300.000 | 2025-09-08 | 2025-09 | 002233       | Transfer   | Loket | Riska   |
| Yuki Erik                    | B10  | 8   |      300.000 | 2025-09-09 | 2025-09 | 002234       | VA         | Loket | Riska   |
| Nico Cristian                | E7   | 8   |      300.000 | 2025-09-09 | 2025-09 | 002235       | VA         | Loket | Riska   |
| Sugandi                      | D8   | 2   |      350.000 | 2025-09-09 | 2025-09 | 002237       | VA         | Loket | Riska   |
| Yelsikasari                  | B9   | 60  |      300.000 | 2025-09-09 | 2025-09 | 002239       | VA         | Loket | Riska   |
| Else                         | DD1  | 2   |      250.000 | 2025-09-09 | 2025-09 | 002240       | VA         | Loket | Riska   |
| Mohammad Syukron             | C6   | 19  |      225.000 | 2025-09-09 | 2025-09 | 002241       | VA         | Loket | Riska   |
| Gusnita                      | B9   | 35  |      300.000 | 2025-09-09 | 2025-09 | 002243       | Transfer   | Loket | Riska   |
| Sonya Elia Chandra           | AX5  | 20  |      200.000 | 2025-09-09 | 2025-09 | 002244       | Transfer   | Loket | Riska   |
| Hans Jakal                   | C1   | 12  |      225.000 | 2025-09-09 | 2025-09 | 002246       | Tunai      | Loket | Riska   |
| Anter Aja                    | JA   | 15  |      300.000 | 2025-09-09 | 2025-09 | 002247       | Tunai      | Loket | Riska   |
| Visillia Linata              | AX3  | 65  |      200.000 | 2025-09-09 | 2025-09 | 002248       | Tunai      | Loket | Riska   |
| Jhon Delon                   | AX5  | 78  |      200.000 | 2025-09-10 | 2025-09 | 002249       | Tunai      | Loket | Riska   |
| Elly Swanti                  | C1   | 11  |      225.000 | 2025-09-10 | 2025-09 | 002250       | VA         | Loket | Riska   |
| Verawati Wijaya              | AX5  | 18  |      200.000 | 2025-09-10 | 2025-09 | 002252       | VA         | Loket | Riska   |
| Charlie Chandra              | AX5  | 38  |      200.000 | 2025-09-10 | 2025-09 | 002253       | VA         | Loket | Riska   |
| Kuswanto                     | AM2  | 19  |      300.000 | 2025-09-10 | 2025-09 | 002256       | VA         | Loket | Riska   |
| Friska                       | AX5  | 57  |      250.000 | 2025-09-10 | 2025-09 | 002257       | Tunai      | Loket | Riska   |
| M Alfian Khairi              | C9   | 7   |      225.000 | 2025-09-10 | 2025-09 | 002259       | Transfer   | Loket | Riska   |
| Abdul Azis Saifuddin         | C11  | 8   |      225.000 | 2025-09-10 | 2025-09 | 002260       | Transfer   | Loket | Riska   |
| Johanna Hermawan             | DD2  | 3   |      250.000 | 2025-09-10 | 2025-09 | 002261       | Transfer   | Loket | Riska   |
| Agus Setiawan                | JA   | 7   |      300.000 | 2025-09-10 | 2025-09 | 002263       | Transfer   | Loket | Riska   |
| Dewi Santosari               | C5   | 9   |      225.000 | 2025-09-11 | 2025-09 | 002265       | VA         | Loket | Riska   |
| Agnes Wiwik                  | B10  | 77  |      300.000 | 2025-09-11 | 2025-09 | 002266       | VA         | Loket | Riska   |
| Yani lestari                 | AX7  | 6   |      350.000 | 2025-09-11 | 2025-09 | 002267       | VA         | Loket | Riska   |
| Fransisca                    | AX7  | 17  |      350.000 | 2025-09-11 | 2025-09 | 002269       | VA         | Loket | Riska   |
| Nicholas Widjaja             | DD1  | 7   |      250.000 | 2025-09-11 | 2025-09 | 002270       | VA         | Loket | Riska   |
| Etri Septivia                | B9   | 59  |      300.000 | 2025-09-11 | 2025-07 | 002272       | Transfer   | Loket | Riska   |
| Go Wie Guan                  | AX8  | 12  |      300.000 | 2025-09-12 | 2025-09 | 002274       | VA         | Loket | Riska   |
| Anastasia Dame               | AX7  | 7   |      350.000 | 2025-09-15 | 2025-09 | 002276       | VA         | Loket | Riska   |
| Teguh Santoso                | C3   | 3   |      225.000 | 2025-09-15 | 2025-09 | 002277       | VA         | Loket | Riska   |
| Arnold Dwi Putra             | AX5  | 26  |      200.000 | 2025-09-15 | 2025-09 | 002278       | VA         | Loket | Riska   |
| Seftiningtyas                | AX3  | 17  |      200.000 | 2025-09-15 | 2025-09 | 002279       | VA         | Loket | Riska   |
| Herson                       | D8   | 3   |      350.000 | 2025-09-15 | 2025-09 | 002284       | Transfer   | Loket | Riska   |
| Herson                       | D8   | 7   |      350.000 | 2025-09-15 | 2025-09 | 002285       | Transfer   | Loket | Riska   |
| Herson                       | C7   | 28  |      225.000 | 2025-09-15 | 2025-09 | 002287       | Transfer   | Loket | Riska   |
| Marliana Sari                | AX5  | 6   |      200.000 | 2025-09-16 | 2025-09 | 002292       | Tunai      | Loket | Riska   |
| Kristianto                   | JA   | 2   |      300.000 | 2025-09-17 | 2025-09 | 002298       | VA         | Loket | Riska   |
| Rinaldy Tarmadi              | AX5  | 39  |      250.000 | 2025-09-17 | 2025-09 | 002299       | VA         | Loket | Riska   |
| Finna Clasera                | B8   | 18  |      300.000 | 2025-09-17 | 2025-09 | 002300       | VA         | Loket | Riska   |
| Rosia Daeli                  | C6   | 27  |      225.000 | 2025-09-17 | 2025-09 | 002301       | VA         | Loket | Riska   |
| Multi Infiniti Pro           | JA   | 8   |      300.000 | 2025-09-18 | 2025-09 | 002302       | VA         | Loket | Riska   |
| Yenni                        | C5   | 12  |      225.000 | 2025-09-18 | 2025-09 | 002303       | VA         | Loket | Riska   |
| Dila Ardila                  | B10  | 25  |      300.000 | 2025-09-19 | 2025-09 | 002306       | VA         | Loket | Riska   |
| Hendry                       | DD1  | 9   |      250.000 | 2025-09-19 | 2025-09 | 002307       | VA         | Loket | Riska   |
| Carolina                     | C1   | 10  |      225.000 | 2025-09-22 | 2025-09 | 002310       | VA         | Loket | Riska   |
| Fransisca Herlin             | B8   | 35  |      300.000 | 2025-09-22 | 2025-09 | 002311       | VA         | Loket | Riska   |
| Siaw Ling                    | AX3  | 89  |      200.000 | 2025-09-22 | 2025-09 | 002312       | VA         | Loket | Riska   |
| Vera S                       | C11  | 28  |      225.000 | 2025-09-22 | 2025-09 | 002316       | Transfer   | Loket | Riska   |
| Yuliana                      | C6   | 31  |      225.000 | 2025-09-22 | 2025-09 | 002318       | Transfer   | Loket | Riska   |
| Sinar Winarni                | AX7  | 5   |      350.000 | 2025-09-23 | 2025-09 | 002320       | VA         | Loket | Riska   |
| Lie Yuandra yeremi           | JA   | 29  |      300.000 | 2025-09-23 | 2025-09 | 002321       | VA         | Loket | Riska   |
| Linda Karlinda               | JA   | 30  |      300.000 | 2025-09-23 | 2025-09 | 002322       | VA         | Loket | Riska   |
| Arlia Anwar                  | B10  | 78  |      300.000 | 2025-09-23 | 2025-09 | 002323       | VA         | Loket | Riska   |
| Arlia Anwar                  | AX6  | 31  |      300.000 | 2025-09-23 | 2025-09 | 002324       | VA         | Loket | Riska   |
| Hadianto                     | C8   | 11  |      225.000 | 2025-09-23 | 2025-09 | 002325       | VA         | Loket | Riska   |
| Denis Sofia Lestari          | AX3  | 55  |      200.000 | 2025-09-23 | 2025-09 | 002327       | Transfer   | Loket | Riska   |
| Siti Fatonah                 | AX5  | 32  |      200.000 | 2025-09-24 | 2025-09 | 002328       | Tunai      | Loket | Riska   |
| Santi                        | AX6  | 25  |      350.000 | 2025-09-24 | 2025-09 | 002329       | VA         | Loket | Riska   |
| Rahdian Hafiz                | DD2  | 17  |      250.000 | 2025-09-24 | 2025-09 | 002330       | VA         | Loket | Riska   |
| Hartinna                     | B9   | 25  |      300.000 | 2025-09-24 | 2025-07 | 002332       | Transfer   | Loket | Riska   |
| Rangga Afyan Dwiokta         | AX6  | 62  |      250.000 | 2025-09-24 | 2025-09 | 002334       | Transfer   | Loket | Riska   |
| Rohani Susi Herlin           | B9   | 10  |      300.000 | 2025-09-24 | 2025-09 | 002335       | Transfer   | Loket | Riska   |
| Muhamad fajrin               | D7   | 8   |      350.000 | 2025-09-25 | 2025-09 | 002336       | VA         | Loket | Riska   |
| Theresia Suyati              | AM2  | 11  |      300.000 | 2025-09-25 | 2025-09 | 002337       | VA         | Loket | Riska   |
| Lidya                        | AX5  | 15  |      250.000 | 2025-09-25 | 2025-09 | 002338       | VA         | Loket | Riska   |
| Edy Hartono                  | JA   | 19  |      300.000 | 2025-09-25 | 2025-09 | 002339       | VA         | Loket | Riska   |
| Henry Tulus                  | DD2  | 5   |      250.000 | 2025-09-25 | 2025-09 | 002342       | Transfer   | Loket | Riska   |
| Noer Hartati                 | C6   | 59  |      225.000 | 2025-09-26 | 2025-09 | 002348       | VA         | Loket | Riska   |
| Ronald Yahya                 | C1   | 2   |      225.000 | 2025-09-26 | 2025-09 | 002349       | VA         | Loket | Riska   |
| Eunike Aryaningrum           | E7   | 35  |      300.000 | 2025-09-26 | 2025-11 | 002350       | VA         | Loket | Riska   |
| Karim Amirullah              | B10  | 16  |      300.000 | 2025-09-26 | 2025-09 | 002351       | VA         | Loket | Riska   |
| Nesa Apriari                 | B8   | 67  |      300.000 | 2025-09-26 | 2025-09 | 002352       | VA         | Loket | Riska   |
| Herlika                      | AX5  | 35  |      250.000 | 2025-09-26 | 2025-09 | 002353       | VA         | Loket | Riska   |
| Kristiandi                   | B9   | 56  |      300.000 | 2025-09-26 | 2025-09 | 002354       | VA         | Loket | Riska   |
| Yudian Fantoni               | B9   | 36  |      300.000 | 2025-09-26 | 2025-09 | 002355       | Tunai      | Loket | Riska   |
| Jhoni Candra                 | C1   | 6   |      225.000 | 2025-09-26 | 2025-09 | 002357       | Transfer   | Loket | Riska   |
| Badai Rosamudra              | AX5  | 56  |      200.000 | 2025-09-29 | 2025-09 | 002360       | Transfer   | Loket | Riska   |
| Pramadasyah                  | B9   | 32  |      300.000 | 2025-09-29 | 2025-09 | 002362       | Transfer   | Loket | Riska   |
| Lukmanul Hakim               | C8   | 9   |      225.000 | 2025-09-29 | 2025-06 | 002365       | Transfer   | Loket | Riska   |
| Shafa Salsabila              | AM2  | 18  |      300.000 | 2025-09-29 | 2025-09 | 002367       | Transfer   | Loket | Riska   |
| Stephanie Setiawan           | DD1  | 6   |      250.000 | 2025-09-29 | 2025-09 | 002369       | Transfer   | Loket | Riska   |
| Mohamad Yusuf                | B8   | 81  |      300.000 | 2025-09-29 | 2025-09 | 002370       | Transfer   | Loket | Riska   |
| Mugi Arsih                   | AX5  | 9   |      250.000 | 2025-09-29 | 2025-09 | 002371       | Transfer   | Loket | Riska   |
| Carmel Anugerah              | E7   | 29  |      300.000 | 2025-09-30 | 2025-09 | 002372       | Transfer   | Loket | Riska   |
| Ameng                        | C11  | 18  |      225.000 | 2025-09-30 | 2025-09 | 002373       | Transfer   | Loket | Riska   |
| Ernest Kurniawan             | DD2  | 8   |      250.000 | 2025-09-30 | 2025-09 | 002374       | Transfer   | Loket | Riska   |
| Bram Wihardja                | AX7  | 31  |      350.000 | 2025-09-30 | 2025-09 | 002375       | Transfer   | Loket | Riska   |
| Ediana Sigalingging          | AM2  | 1   |      300.000 | 2025-09-30 | 2025-09 | 002376       | Tunai      | Loket | Riska   |
| Ediana Sigalingging          | AM2  | 2   |      300.000 | 2025-09-30 | 2025-09 | 002377       | Tunai      | Loket | Riska   |

## Oktober 2025

| Nama                   | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC    | Petugas |
| ---------------------- | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ------ | ------- |
| Rieckson Wullur        | JA   | 23  |      300.000 | 2025-10-01 | 2025-09 | 002380       | Transfer   | Online | Ari     |
| Indomaret Grand Duta 2 | E7   | 27  |      300.000 | 2025-10-01 | 2025-09 | 002385       | Tunai      | Ofline | Ari     |
| Indomaret Grand Duta 2 | E7   | 28  |      300.000 | 2025-10-01 | 2025-09 | 002386       | Tunai      | Ofline | Ari     |
| Rizky Arif             | AX8  | 5   |      300.000 | 2025-10-02 | 2025-09 | 002391       | Tunai      | Loket  | Indra   |
| Mohammad Ammar         | B9   | 18  |      300.000 | 2025-10-03 | 2025-09 | 002393       | Tunai      | Loket  | Riska   |
| Budiman                | C6   | 61  |      225.000 | 2025-10-03 | 2025-01 | 002415       | Tunai      | Loket  | Riska   |
| Novriyansyah Karna     | AM1  | 11  |      300.000 | 2025-10-03 | 2025-09 | 002423       | Transfer   | Loket  | Riska   |
| Iwan Kiswanto          | B7   | 39  |      300.000 | 2025-10-06 | 2025-09 | 002445       | Transfer   | Loket  | Riska   |
| Sukardi                | AX5  | 21  |      250.000 | 2025-10-06 | 2025-07 | 002446       | Transfer   | Loket  | Riska   |
| Sinta Dewi             | AX5  | 33  |      250.000 | 2025-10-06 | 2025-09 | 002450       | Transfer   | Loket  | Riska   |
| Vina Karya             | AL2  | 11  |      300.000 | 2025-10-06 | 2025-09 | 002453       | Tunai      | Loket  | Riska   |
| Lusia Tungadi          | D8   | 11  |      350.000 | 2025-10-08 | 2025-09 | 002487       | Transfer   | Loket  | Riska   |
| Jaya Iskandar          | C9   | 8   |      225.000 | 2025-10-08 | 2025-09 | 002488       | Tunai      | Loket  | Riska   |
| Agus Tanzil            | D7   | 3   |      350.000 | 2025-10-08 | 2025-09 | 002492       | Transfer   | Loket  | Riska   |
| Euis Tuty              | AM2  | 6   |      300.000 | 2025-10-09 | 2025-09 | 002498       | Tunai      | Loket  | Riska   |
| Euis Tuty              | JA   | 21  |      300.000 | 2025-10-09 | 2025-09 | 002499       | Tunai      | Loket  | Riska   |
| M E Diah Ayu           | B10  | 27  |      300.000 | 2025-10-10 | 2025-09 | 002504       | Transfer   | Online | Ari     |
| Nurul Kurnia Wati      | B10  | 33  |      300.000 | 2025-10-10 | 2025-09 | 002510       | Transfer   | Online | Ari     |
| Herson                 | AX9  | 38  |      300.000 | 2025-10-13 | 2025-09 | 002519       | Transfer   | Loket  | Riska   |
| Herson                 | C6   | 55  |      225.000 | 2025-10-13 | 2025-09 | 002520       | Transfer   | Loket  | Riska   |
| Herson                 | AL2  | 5   |      300.000 | 2025-10-13 | 2025-09 | 002522       | Transfer   | Loket  | Riska   |
| Rizki Andriani         | B9   | 6   |      300.000 | 2025-10-13 | 2025-01 | 002526       | Transfer   | Loket  | Riska   |
| Anista Retno           | JA   | 16  |      300.000 | 2025-10-14 | 2025-09 | 002538       | Transfer   | Loket  | Riska   |
| Jonathan Surya         | B9   | 79  |      300.000 | 2025-10-20 | 2025-07 | 002559       | Transfer   | Loket  | Riska   |
| Bayu Septian           | B9   | 81  |      300.000 | 2025-10-20 | 2024-02 | 002561       | Transfer   | Online | Ari     |
| Dennie                 | B9   | 29  |      300.000 | 2025-10-24 | 2025-09 | 002577       | Transfer   | Loket  | Riska   |
| Liyanawati             | AX7  | 16  |      350.000 | 2025-10-24 | 2025-11 | 002578       | Transfer   | Loket  | Riska   |
| Handrian Wijaya        | DD1  | 5   |      275.000 | 2025-10-24 | 2025-11 | 002579       | Transfer   | Loket  | Riska   |
| Meti Oktavia           | DD2  | 2   |      275.000 | 2025-10-27 | 2025-11 | 002590       | Transfer   | Loket  | Riska   |
| Andrew                 | C8   | 3   |      225.000 | 2025-10-27 | 2025-09 | 002591       | Transfer   | Loket  | Riska   |
| Wulan Januri           | C6   | 37  |      250.000 | 2025-10-27 | 2025-11 | 002596       | Transfer   | Loket  | Riska   |
| Yulianti               | B9   | 7   |      300.000 | 2025-10-28 | 2025-09 | 002599       | Transfer   | Loket  | Riska   |
| Lukmanul Hakim         | C8   | 9   |      225.000 | 2025-10-28 | 2025-07 | 002602       | Transfer   | Loket  | Riska   |
| Ferry Sofianto         | DD2  | 12  |      275.000 | 2025-10-31 | 2025-11 | 002627       | Transfer   | Loket  | Riska   |
| Stefanus Andre         | AX9  | 50  |      300.000 | 2025-10-31 | 2025-11 | 002633       | Transfer   | Loket  | Riska   |

## November 2025

| Nama                         | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC   | Petugas |
| ---------------------------- | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ----- | ------- |
| Yusron Fauzi                 | C8   | 12  |      250.000 | 2025-11-03 | 2025-11 | 002635       | Tunai      | Loket | Riska   |
| Ajeng                        | DD1  | 17  |      275.000 | 2025-11-03 | 2025-11 | 002636       | VA         | Loket | Riska   |
| Giffari Lestionanda          | B8   | 27  |      300.000 | 2025-11-03 | 2025-11 | 002637       | VA         | Loket | Riska   |
| Chandra                      | AX6  | 19  |      350.000 | 2025-11-03 | 2025-11 | 002638       | VA         | Loket | Riska   |
| Rita                         | B10  | 21  |      300.000 | 2025-11-03 | 2025-11 | 002641       | VA         | Loket | Riska   |
| Evi Yulianti                 | AX3  | 35  |      200.000 | 2025-11-03 | 2025-11 | 002642       | VA         | Loket | Riska   |
| Purnawati SE                 | AX6  | 9   |      350.000 | 2025-11-03 | 2025-11 | 002643       | VA         | Loket | Riska   |
| Noviani Harianto             | C11  | 10  |      250.000 | 2025-11-03 | 2025-11 | 002644       | VA         | Loket | Riska   |
| Mohammad Syukron             | C6   | 19  |      250.000 | 2025-11-03 | 2025-11 | 002645       | VA         | Loket | Riska   |
| Nani Junaeni                 | AX8  | 19  |      300.000 | 2025-11-03 | 2025-09 | 002647       | Transfer   | Loket | Riska   |
| Albertus Chandra             | AX8  | 60  |      300.000 | 2025-11-03 | 2025-11 | 002648       | Transfer   | Loket | Riska   |
| Tio Fani                     | AX8  | 6   |      300.000 | 2025-11-03 | 2025-11 | 002650       | Transfer   | Loket | Riska   |
| Diyah Fajar Sari             | C1   | 5   |      250.000 | 2025-11-03 | 2025-11 | 002651       | Transfer   | Loket | Riska   |
| Melisa Apriyani              | AX5  | 80  |      200.000 | 2025-11-03 | 2025-11 | 002652       | Transfer   | Loket | Riska   |
| Bargani                      | C10  | 6   |      250.000 | 2025-11-03 | 2025-11 | 002653       | Tunai      | Loket | Riska   |
| Viyan Andhika                | AX7  | 19  |      350.000 | 2025-11-03 | 2025-11 | 002657       | Transfer   | Loket | Riska   |
| Julio Andrian                | AX3  | 29  |      200.000 | 2025-11-03 | 2025-11 | 002658       | Transfer   | Loket | Riska   |
| Budhi Mulyadi                | DD3  | 3   |      275.000 | 2025-11-03 | 2025-11 | 002659       | Transfer   | Loket | Riska   |
| Hary                         | C9   | 11  |      225.000 | 2025-11-03 | 2025-02 | 002660       | Transfer   | Loket | Riska   |
| Tanu Wijaya                  | DD2  | 7   |      275.000 | 2025-11-03 | 2025-11 | 002661       | Transfer   | Loket | Riska   |
| Muhammad Fachdilah           | AX6  | 70  |      250.000 | 2025-11-03 | 2025-11 | 002662       | Transfer   | Loket | Riska   |
| Kartika Melanda              | AX6  | 27  |      350.000 | 2025-11-03 | 2025-11 | 002663       | Transfer   | Loket | Riska   |
| Jovi Maryadi                 | AX5  | 11  |      250.000 | 2025-11-03 | 2025-09 | 002665       | Transfer   | Loket | Riska   |
| Christy Veronika             | B10  | 3   |      300.000 | 2025-11-03 | 2025-06 | 002668       | Transfer   | Loket | Riska   |
| Luman                        | AX7  | 37  |      350.000 | 2025-11-03 | 2025-11 | 002670       | Transfer   | Loket | Riska   |
| Indomaret Grand Duta 1       | JA   | 10  |      300.000 | 2025-11-03 | 2025-11 | 002673       | Tunai      | Loket | Riska   |
| Indomaret Grand Duta 1       | JA   | 11  |      300.000 | 2025-11-03 | 2025-11 | 002674       | Tunai      | Loket | Riska   |
| Melina                       | AX8  | 36  |      300.000 | 2025-11-04 | 2025-11 | 002676       | VA         | Loket | Riska   |
| Linda Wati                   | B10  | 39  |      300.000 | 2025-11-04 | 2025-11 | 002677       | VA         | Loket | Riska   |
| Deri Yatno                   | AX7  | 29  |      350.000 | 2025-11-04 | 2025-11 | 002678       | VA         | Loket | Riska   |
| Bambang Supriyadi            | C7   | 38  |      250.000 | 2025-11-04 | 2025-11 | 002679       | VA         | Loket | Riska   |
| Ervina                       | AM1  | 5   |      300.000 | 2025-11-04 | 2025-11 | 002680       | VA         | Loket | Riska   |
| Ervina                       | AM1  | 3   |      300.000 | 2025-11-04 | 2025-11 | 002681       | VA         | Loket | Riska   |
| Dedi Sugiarto                | DD1  | 19  |      275.000 | 2025-11-04 | 2025-11 | 002682       | VA         | Loket | Riska   |
| Imas Rahayu Ningsih          | AX8  | 25  |      300.000 | 2025-11-04 | 2025-11 | 002683       | VA         | Loket | Riska   |
| Tain Yunan                   | AM1  | 2   |      300.000 | 2025-11-04 | 2025-11 | 002684       | VA         | Loket | Riska   |
| Kartika Puspa                | AX7  | 8   |      350.000 | 2025-11-04 | 2025-11 | 002685       | VA         | Loket | Riska   |
| Silviana Victoria            | DD1  | 16  |      275.000 | 2025-11-04 | 2025-11 | 002686       | VA         | Loket | Riska   |
| Abdul Azis Saifuddin         | C11  | 8   |      250.000 | 2025-11-04 | 2025-11 | 002689       | Transfer   | Loket | Riska   |
| Dendy                        | AX3  | 91  |      200.000 | 2025-11-04 | 2025-11 | 002690       | Transfer   | Loket | Riska   |
| Wirya Dharmawan              | AX3  | 71  |      200.000 | 2025-11-04 | 2025-11 | 002691       | Transfer   | Loket | Riska   |
| Lista Akdriaty               | C3   | 7   |      250.000 | 2025-11-05 | 2025-11 | 002695       | VA         | Loket | Riska   |
| Noviana                      | AX7  | 33  |      350.000 | 2025-11-05 | 2025-11 | 002696       | VA         | Loket | Riska   |
| Devi Febrian                 | DD1  | 18  |      275.000 | 2025-11-05 | 2025-11 | 002698       | VA         | Loket | Riska   |
| Mohamad Arif                 | E7   | 18  |      300.000 | 2025-11-05 | 2025-11 | 002699       | VA         | Loket | Riska   |
| Yenni                        | C9   | 6   |      250.000 | 2025-11-05 | 2025-11 | 002700       | VA         | Loket | Riska   |
| Herman                       | DD3  | 10  |      275.000 | 2025-11-05 | 2025-11 | ,            | VA         | Loket | Riska   |
| Multi Infiniti Pro           | JA   | 8   |      300.000 | 2025-11-05 | 2025-11 | 002702       | VA         | Loket | Riska   |
| Linda Apriani                | AX5  | 70  |      200.000 | 2025-11-05 | 2025-11 | 002703       | VA         | Loket | Riska   |
| Hanafi                       | C7   | 26  |      250.000 | 2025-11-05 | 2025-11 | 002704       | VA         | Loket | Riska   |
| Anugerah Primarasa           | DD3  | 6   |      275.000 | 2025-11-05 | 2025-11 | 002707       | Transfer   | Loket | Riska   |
| Andini Laurensia             | C2   | 22  |      250.000 | 2025-11-05 | 2025-11 | 002708       | Transfer   | Loket | Riska   |
| Evi / GBI                    | JA   | 1   |      300.000 | 2025-11-05 | 2025-11 | 002710       | Transfer   | Loket | Riska   |
| Siat Suk Jin                 | C5   | 5   |      225.000 | 2025-11-05 | 2025-09 | 002711       | Tunai      | Loket | Riska   |
| Putri Alvina                 | AX7  | 27  |      350.000 | 2025-11-05 | 2025-11 | 002712       | Transfer   | Loket | Riska   |
| Tri Septiyanti               | E7   | 7   |      300.000 | 2025-11-05 | 2025-09 | 002717       | Transfer   | Loket | Riska   |
| Friciel Yael                 | B8   | 79  |      300.000 | 2025-11-06 | 2025-11 | 002720       | VA         | Loket | Riska   |
| Drya Nursanti                | AX5  | 72  |      200.000 | 2025-11-06 | 2025-11 | 002721       | VA         | Loket | Riska   |
| Charlie Chandra              | AX5  | 38  |      200.000 | 2025-11-06 | 2025-11 | 002722       | VA         | Loket | Riska   |
| Hanindya Puspita             | JA   | 5   |      300.000 | 2025-11-06 | 2025-11 | 002723       | VA         | Loket | Riska   |
| Rika                         | AM2  | 10  |      300.000 | 2025-11-06 | 2025-11 | 002724       | VA         | Loket | Riska   |
| Friska                       | AX5  | 57  |      250.000 | 2025-11-06 | 2025-11 | 002726       | Tunai      | Loket | Riska   |
| Sapto Rini Rushapsari        | AX6  | 7   |      350.000 | 2025-11-06 | 2025-11 | 002728       | Transfer   | Loket | Riska   |
| Sonya Elia Chandra           | AX5  | 20  |      200.000 | 2025-11-06 | 2025-11 | 002730       | Transfer   | Loket | Riska   |
| Anita Carolina               | B10  | 32  |      300.000 | 2025-11-07 | 2025-11 | 002735       | VA         | Loket | Riska   |
| Yuki Erik                    | B10  | 8   |      300.000 | 2025-11-07 | 2025-11 | 002736       | VA         | Loket | Riska   |
| Dessy                        | AX7  | 55  |      350.000 | 2025-11-07 | 2025-11 | 002737       | VA         | Loket | Riska   |
| Henni Johanes                | AX5  | 59  |      250.000 | 2025-11-07 | 2025-11 | 002738       | Transfer   | Loket | Riska   |
| Siti Suprapti                | AX2  | 8&9 |      500.000 | 2025-11-07 | 2025-11 | 002739       | Transfer   | Loket | Riska   |
| Andhi Indarto                | E7   | 12  |      300.000 | 2025-11-07 | 2025-11 | 002740       | Transfer   | Loket | Riska   |
| Lisa Loandha                 | C11  | 20  |      250.000 | 2025-11-07 | 2025-11 | 002741       | Transfer   | Loket | Riska   |
| Arlia Anwar                  | B10  | 78  |      300.000 | 2025-11-07 | 2025-11 | 002742       | Transfer   | Loket | Riska   |
| Regina Adytia                | AX8  | 3   |      300.000 | 2025-11-10 | 2025-11 | 002744       | VA         | Loket | Riska   |
| Rike Saxena                  | C9   | 5   |      250.000 | 2025-11-10 | 2025-11 | 002745       | VA         | Loket | Riska   |
| Andri Mustakim               | AX5  | 5   |      250.000 | 2025-11-10 | 2025-11 | 002746       | VA         | Loket | Riska   |
| Arlia Anwar                  | AX6  | 31  |      350.000 | 2025-11-10 | 2025-11 | 002747       | VA         | Loket | Riska   |
| Herlisa Resti                | B8   | 50  |      300.000 | 2025-11-10 | 2025-11 | 002748       | VA         | Loket | Riska   |
| Fransisca Natalia            | C11  | 16  |      250.000 | 2025-11-10 | 2025-11 | 002749       | VA         | Loket | Riska   |
| Eriyono budi                 | AX3  | 69  |      200.000 | 2025-11-10 | 2025-11 | 002750       | VA         | Loket | Riska   |
| Hans Jakal                   | C1   | 12  |      250.000 | 2025-11-10 | 2025-11 | 002751       | Tunai      | Loket | Riska   |
| Jangkau Lintas Nusantara     | E7   | 50  |      300.000 | 2025-11-10 | 2025-11 | 002752       | Transfer   | Loket | Riska   |
| Stanley / Dompet anak bangsa | AX5  | 23  |      250.000 | 2025-11-10 | 2025-11 | 002754       | Transfer   | Loket | Riska   |
| Herson                       | D8   | 3   |      350.000 | 2025-11-10 | 2025-11 | 002758       | Transfer   | Loket | Riska   |
| Herson                       | D8   | 7   |      350.000 | 2025-11-10 | 2025-11 | 002759       | Transfer   | Loket | Riska   |
| Herson                       | C7   | 28  |      250.000 | 2025-11-10 | 2025-11 | 002760       | Transfer   | Loket | Riska   |
| Johanna Hermawan             | DD2  | 3   |      275.000 | 2025-11-10 | 2025-11 | 002762       | Transfer   | Loket | Riska   |
| M Alfian Khairi              | C9   | 7   |      250.000 | 2025-11-10 | 2025-11 | 002763       | Transfer   | Loket | Riska   |
| Marliana Sari                | AX5  | 6   |      200.000 | 2025-11-11 | 2025-11 | 002765       | Tunai      | Loket | Riska   |
| Kuswanto                     | AM2  | 19  |      300.000 | 2025-11-11 | 2025-11 | 002766       | VA         | Loket | Riska   |
| Elly Swanti                  | C1   | 11  |      250.000 | 2025-11-11 | 2025-11 | 002767       | VA         | Loket | Riska   |
| Nico Cristian                | E7   | 8   |      300.000 | 2025-11-11 | 2025-11 | 002768       | VA         | Loket | Riska   |
| Agnes Wiwik                  | B10  | 77  |      300.000 | 2025-11-11 | 2025-11 | 002769       | VA         | Loket | Riska   |
| Anastasia Dame               | AX7  | 7   |      350.000 | 2025-11-11 | 2025-11 | 002770       | VA         | Loket | Riska   |
| Else                         | DD1  | 2   |      275.000 | 2025-11-11 | 2025-11 | 002771       | VA         | Loket | Riska   |
| Felix Hadianto               | AX5  | 76  |      200.000 | 2025-11-11 | 2025-11 | 002772       | VA         | Loket | Riska   |
| Yelsikasari                  | B9   | 60  |      300.000 | 2025-11-11 | 2025-11 | 002773       | VA         | Loket | Riska   |
| Arie Wibowo                  | AM1  | 7   |      300.000 | 2025-11-11 | 2025-11 | 002775       | Transfer   | Loket | Riska   |
| Nova Diana                   | B10  | 6   |      300.000 | 2025-11-11 | 2025-11 | 002776       | Transfer   | Loket | Riska   |
| Jhon Delon                   | AX5  | 78  |      200.000 | 2025-11-11 | 2025-11 | 002777       | Tunai      | Loket | Riska   |
| Dewi Santosari               | C5   | 9   |      250.000 | 2025-11-12 | 2025-11 | 002778       | VA         | Loket | Riska   |
| Irwan                        | AX3  | 37  |      200.000 | 2025-11-12 | 2025-11 | 002779       | VA         | Loket | Riska   |
| Selvy                        | AX8  | 7   |      300.000 | 2025-11-12 | 2025-11 | 002781       | Transfer   | Loket | Riska   |
| Leo Baratanata               | DD1  | 15  |      275.000 | 2025-11-13 | 2025-11 | 002784       | VA         | Loket | Riska   |
| Rinaldy Tarmadi              | AX5  | 39  |      250.000 | 2025-11-13 | 2025-11 | 002785       | VA         | Loket | Riska   |
| Arnold Dwi Putra             | AX5  | 26  |      200.000 | 2025-11-13 | 2025-11 | 002786       | VA         | Loket | Riska   |
| Tham Alifin                  | AX5  | 29  |      250.000 | 2025-11-13 | 2025-11 | 002789       | Transfer   | Loket | Riska   |
| Tham Alifin                  | AX6  | 6   |      250.000 | 2025-11-13 | 2025-11 | 002790       | Transfer   | Loket | Riska   |
| Hendry                       | DD1  | 9   |      275.000 | 2025-11-14 | 2025-11 | 002791       | VA         | Loket | Riska   |
| Teguh Santoso                | C3   | 3   |      250.000 | 2025-11-14 | 2025-11 | 002793       | VA         | Loket | Riska   |
| Siti Nurwati                 | AX6  | 17  |      350.000 | 2025-11-14 | 2025-11 | 002795       | VA         | Loket | Riska   |
| Sabeth                       | AX8  | 30  |      300.000 | 2025-11-17 | 2025-11 | 002800       | VA         | Loket | Riska   |
| Shafa Salsabila              | AM2  | 18  |      300.000 | 2025-11-17 | 2025-11 | 002801       | VA         | Loket | Riska   |
| Yulin                        | AX8  | 39  |      300.000 | 2025-11-17 | 2025-11 | 002802       | VA         | Loket | Riska   |
| Benny Supardjo               | C6   | 29  |      250.000 | 2025-11-17 | 2025-11 | 002803       | Transfer   | Loket | Riska   |
| GSJA Abbacare                | AL2  | 12  |      300.000 | 2025-11-17 | 2025-11 | 002804       | Transfer   | Loket | Riska   |
| Eddy                         | AX8  | 53  |      300.000 | 2025-11-17 | 2025-11 | 002805       | Transfer   | Loket | Riska   |
| Hanni Yesy                   | C6   | 33  |      250.000 | 2025-11-17 | 2025-11 | 002806       | Transfer   | Loket | Riska   |
| Go Wie Guan                  | AX8  | 12  |      300.000 | 2025-11-18 | 2025-11 | 002813       | VA         | Loket | Riska   |
| Sinar Winarni                | AX7  | 5   |      350.000 | 2025-11-19 | 2025-11 | 002815       | VA         | Loket | Riska   |
| Simon Liu                    | AX6  | 32  |      250.000 | 2025-11-19 | 2025-11 | 002816       | VA         | Loket | Riska   |
| Rully Angga                  | AX7  | 12  |      350.000 | 2025-11-19 | 2025-11 | 002817       | VA         | Loket | Riska   |
| Vivin Rosalia                | C10  | 2   |      250.000 | 2025-11-19 | 2025-11 | 002818       | VA         | Loket | Riska   |
| Gusnita                      | B9   | 35  |      300.000 | 2025-11-19 | 2025-11 | 002827       | Transfer   | Loket | Riska   |
| Fransisca herlin             | B8   | 35  |      300.000 | 2025-11-20 | 2025-11 | 002828       | VA         | Loket | Riska   |
| Kristianto                   | JA   | 2   |      300.000 | 2025-11-20 | 2025-11 | 002829       | VA         | Loket | Riska   |
| Santi                        | AX6  | 25  |      350.000 | 2025-11-20 | 2025-11 | 002831       | Tunai      | Loket | Riska   |
| Cedy Chandra                 | AX6  | 3   |      350.000 | 2025-11-21 | 2025-11 | 002832       | VA         | Loket | Riska   |
| Indrawan                     | AX3  | 39  |      200.000 | 2025-11-21 | 2025-11 | 002833       | VA         | Loket | Riska   |
| Sugandi                      | D8   | 2   |      350.000 | 2025-11-21 | 2025-11 | 002834       | VA         | Loket | Riska   |
| Fransisca                    | AX7  | 17  |      350.000 | 2025-11-24 | 2025-11 | 002839       | VA         | Loket | Riska   |
| Siti Fatonah                 | AX5  | 32  |      200.000 | 2025-11-24 | 2025-11 | 002840       | Tunai      | Loket | Riska   |
| Nicholas Widjaja             | DD1  | 7   |      250.000 | 2025-11-24 | 2025-11 | 002842       | VA         | Loket | Riska   |
| Rossela                      | C6   | 53  |      225.000 | 2025-11-24 | 2025-09 | 002845       | Transfer   | Loket | Riska   |
| Yuliana                      | C6   | 31  |      250.000 | 2025-11-24 | 2025-11 | 002846       | Transfer   | Loket | Riska   |
| Etri Septivia                | B9   | 59  |      300.000 | 2025-11-24 | 2025-09 | 002847       | Transfer   | Loket | Riska   |
| Riyandi Susanto              | C11  | 28  |      250.000 | 2025-11-24 | 2025-11 | 002848       | Transfer   | Loket | Riska   |
| Hadianto                     | C8   | 11  |      250.000 | 2025-11-25 | 2025-11 | 002849       | VA         | Loket | Riska   |
| Denis Sofia Lestari          | AX3  | 55  |      200.000 | 2025-11-25 | 2025-11 | 002852       | VA         | Loket | Riska   |
| Dila Ardila                  | B10  | 25  |      300.000 | 2025-11-25 | 2025-11 | 002853       | VA         | Loket | Riska   |
| Rohani Susi Herlin           | B9   | 10  |      300.000 | 2025-11-25 | 2025-11 | 002854       | Transfer   | Loket | Riska   |
| Meliana                      | C7   | 58  |      250.000 | 2025-11-25 | 2025-11 | 002857       | Transfer   | Loket | Riska   |
| Edy Hartono                  | JA   | 19  |      300.000 | 2025-11-26 | 2025-11 | 002864       | VA         | Loket | Riska   |
| Herlika                      | AX5  | 35  |      250.000 | 2025-11-26 | 2025-11 | 002865       | VA         | Loket | Riska   |
| Jonathan                     | AX3  | 5   |      200.000 | 2025-11-26 | 2025-06 | 002867       | Transfer   | Loket | Riska   |
| Pie Sian                     | AX8  | 62  |      300.000 | 2025-11-26 | 2025-11 | 002869       | Transfer   | Loket | Riska   |
| Badai Rosamudra              | AX5  | 56  |      200.000 | 2025-11-27 | 2025-11 | 002871       | VA         | Loket | Riska   |
| Nesa Apriari                 | B8   | 67  |      300.000 | 2025-11-27 | 2025-11 | 002873       | VA         | Loket | Riska   |
| Yani Lestari                 | AX7  | 6   |      350.000 | 2025-11-27 | 2025-11 | 002874       | VA         | Loket | Riska   |
| Konnie Gunawan               | B9   | 58  |      300.000 | 2025-11-27 | 2025-11 | 002875       | Transfer   | Loket | Riska   |
| Henry Tulus                  | DD2  | 5   |      275.000 | 2025-11-27 | 2025-11 | 002876       | Transfer   | Loket | Riska   |
| Stephanie Setiawan           | DD1  | 6   |      275.000 | 2025-11-27 | 2025-11 | 002878       | Transfer   | Loket | Riska   |
| Sahrial Lubis                | C2   | 27  |      250.000 | 2025-11-28 | 2025-11 | 002880       | Tunai      | Loket | Riska   |
| Ediana Sigalingging          | AM2  | 1   |      300.000 | 2025-11-28 | 2025-11 | 002882       | Tunai      | Loket | Riska   |
| Ediana Sigalingging          | AM2  | 2   |      300.000 | 2025-11-28 | 2025-11 | 002883       | Tunai      | Loket | Riska   |
| Carmel Anugerah              | E7   | 29  |      300.000 | 2025-11-28 | 2025-11 | 002884       | Transfer   | Loket | Riska   |
| M E Diah Ayu                 | B10  | 27  |      300.000 | 2025-11-28 | 2025-11 | 002890       | Transfer   | Loket | Riska   |
| Karim Amirullah              | B10  | 16  |      300.000 | 2025-11-29 | 2025-11 | 002892       | VA         | Loket | Riska   |
| Ronald Yahya                 | C1   | 2   |      250.000 | 2025-11-29 | 2025-11 | 002893       | VA         | Loket | Riska   |
| Jhoni Candra                 | C1   | 6   |      250.000 | 2025-11-29 | 2025-11 | 002894       | Transfer   | Loket | Riska   |
| Ameng                        | C11  | 18  |      250.000 | 2025-11-29 | 2025-11 | 002896       | Transfer   | Loket | Riska   |
| Rangga Afyan Dwiokta         | AX6  | 62  |      250.000 | 2025-11-30 | 2025-11 | 002897       | Transfer   | Loket | Riska   |
| Mugi Arsih                   | AX5  | 9   |      250.000 | 2025-11-30 | 2025-11 | 002898       | Transfer   | Loket | Riska   |
| Reza Satria                  | AX6  | 66  |      250.000 | 2025-11-30 | 2025-11 | 002900       | Transfer   | Loket | Riska   |
| Juliana Putri                | E7   | 2   |      300.000 | 2025-11-30 | 2025-11 | 002903       | Transfer   | Loket | Riska   |

## Desember 2025

| Nama               | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC     | Petugas |
| ------------------ | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ------- | ------- |
| Christy Veronika   | B10  | 3   |      300.000 | 2025-12-01 | 2025-07 | 002908       | Transfer   | Loket   | Riska   |
| Pramadasyah        | B9   | 32  |      300.000 | 2025-12-01 | 2025-11 | 002909       | Transfer   | Loket   | Riska   |
| Ernest Kurniawan   | DD2  | 8   |      275.000 | 2025-12-01 | 2025-11 | 002910       | Transfer   | Loket   | Riska   |
| Rieckson Wulur     | JA   | 23  |      300.000 | 2025-12-01 | 2025-11 | 002915       | Transfer   | Loket   | Riska   |
| Rieckson Wulur     | DD1  | 20  |      275.000 | 2025-12-01 | 2025-11 | 002916       | Transfer   | Loket   | Riska   |
| Yudian Fantoni     | B9   | 36  |      300.000 | 2025-12-02 | 2025-11 | 002917       | Tunai      | Loket   | Riska   |
| Sukardi            | AX5  | 21  |      250.000 | 2025-12-02 | 2025-09 | 002918       | Transfer   | Loket   | Riska   |
| Novriyansyah Karna | AM1  | 11  |      300.000 | 2025-12-02 | 2025-11 | 002919       | Transfer   | Loket   | Riska   |
| Kristiandi         | B9   | 56  |      300.000 | 2025-12-02 | 2025-11 | 002924       | Transfer   | Loket   | Riska   |
| Lusia Tungadi      | D8   | 11  |      350.000 | 2025-12-03 | 2025-11 | 002931       | Transfer   | Loket   | Riska   |
| Carolina           | C1   | 10  |      250.000 | 2025-12-03 | 2025-11 | 002936       | Transfer   | Loket   | Riska   |
| Lilih Yuliani      | AX5  | 36  |      200.000 | 2025-12-03 | 2025-11 | 002937       | Transfer   | Loket   | Riska   |
| Vina Karya         | AL2  | 11  |      300.000 | 2025-12-04 | 2025-11 | 002965       | Tunai      | Loket   | Riska   |
| Rahmawati          | DD3  | 6   |      275.000 | 2025-12-04 | 2026-01 | 002967       | Transfer   | Loket   | Riska   |
| Mohamad Yusuf      | B8   | 81  |      300.000 | 2025-12-04 | 2025-11 | 002968       | Transfer   | Loket   | Riska   |
| Sinta Dewi         | AX5  | 33  |      250.000 | 2025-12-04 | 2025-11 | 002971       | Transfer   | Loket   | Riska   |
| Stefanus           | C3   | 9   |      250.000 | 2025-12-08 | 2025-11 | 003011       | Transfer   | Loket   | Riska   |
| Mohammad Ammar     | B9   | 18  |      300.000 | 2025-12-09 | 2025-11 | 003022       | Tunai      | Loket   | Riska   |
| Budiman            | C6   | 61  |      225.000 | 2025-12-09 | 2025-02 | 003023       | Tunai      | Loket   | Riska   |
| Euis Tuty          | AM2  | 6   |      300.000 | 2025-12-10 | 2025-11 | 003032       | Tunai      | Offline | Ari     |
| Hon King Wie       | C2   | 20  |      225.000 | 2025-12-10 | 2025-09 | 003034       | Transfer   | Online  | Ari     |
| Hon King Wie       | C2   | 20  |      225.000 | 2025-12-10 | 2025-10 | 003035       | Transfer   | Online  | Ari     |
| Idrus              | E7   | 10  |      300.000 | 2025-12-10 | 2025-11 | 003038       | Transfer   | Online  | Ari     |
| Natasya Veronica   | B10  | 30  |      300.000 | 2025-12-10 | 2025-11 | 003044       | Transfer   | Online  | Ari     |
| Herson             | AX9  | 38  |      300.000 | 2025-12-11 | 2025-11 | 003045       | Transfer   | Loket   | Riska   |
| Herson             | C6   | 55  |      250.000 | 2025-12-11 | 2025-11 | 003046       | Transfer   | Loket   | Riska   |
| Herson             | AL2  | 5   |      300.000 | 2025-12-11 | 2025-11 | 003048       | Transfer   | Loket   | Riska   |
| Hani Yesy          | C6   | 33  |      250.000 | 2025-12-11 | 2025-12 | 003056       | Transfer   | Loket   | Riska   |
| Jaya Iskandar      | C9   | 8   |      250.000 | 2025-12-15 | 2025-11 | 003073       | Tunai      | Loket   | Riska   |
| Liyanawati         | AX7  | 16  |      350.000 | 2025-12-16 | 2026-01 | 003077       | Transfer   | Loket   | Riska   |
| Dennie             | B9   | 29  |      300.000 | 2025-12-22 | 2025-11 | 003102       | Transfer   | Loket   | Riska   |
| Andrew             | C8   | 3   |      250.000 | 2025-12-22 | 2025-11 | 003106       | Transfer   | Loket   | Riska   |
| Lukmanul Hakim     | C8   | 9   |      250.000 | 2025-12-23 | 2025-09 | 003113       | Transfer   | Loket   | Riska   |
| Jonathan           | AX3  | 5   |      200.000 | 2025-12-23 | 2025-07 | 003114       | Transfer   | Loket   | Riska   |
| Handrian Wijaya    | DD1  | 5   |      275.000 | 2025-12-24 | 2026-01 | 003124       | Transfer   | Loket   | Riska   |
| Meti Oktavia       | DD2  | 2   |      275.000 | 2025-12-26 | 2026-01 | 003132       | Transfer   | Loket   | Riska   |
| Wulan Januri       | C6   | 37  |      250.000 | 2025-12-29 | 2026-01 | 003151       | Transfer   | Loket   | Riska   |
| Stefanus Andre     | AX9  | 50  |      300.000 | 2025-12-30 | 2026-01 | 003158       | Transfer   | Online  | Ari     |

## Januari 2026

| Nama                               | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC    | Petugas |
| ---------------------------------- | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ------ | ------- |
| Meliana                            | C7   | 58  |      250.000 | 2026-01-02 | 2026-01 | 00001        | Transfer   | Online | Ari     |
| Rezky Iriansyah Anugrah            | B9   | 78  |      300.000 | 2026-01-02 | 2026-01 | 00002        | Transfer   | Online | Ari     |
| Tanu Wijaya                        | DD2  | 7   |      275.000 | 2026-01-02 | 2026-01 | 00003        | Transfer   | Online | Ari     |
| Melisa Apriyani                    | AX5  | 80  |      200.000 | 2026-01-02 | 2026-01 | 00004        | Transfer   | Online | Ari     |
| Budhi Mulyadi                      | DD3  | 3   |      275.000 | 2026-01-02 | 2026-01 | 00005        | Transfer   | Online | Ari     |
| Selvy                              | AX8  | 7   |      300.000 | 2026-01-02 | 2026-01 | 00006        | Transfer   | Online | Ari     |
| Ordie                              | B8   | 10  |      300.000 | 2026-01-02 | 2026-01 | 00009        | Transfer   | Online | Ari     |
| M Alfian Khairi                    | C9   | 7   |      250.000 | 2026-01-02 | 2026-01 | 00010        | Transfer   | Online | Ari     |
| Muhammad Fachdillah                | AX6  | 70  |      250.000 | 2026-01-02 | 2026-01 | 00011        | Transfer   | Online | Ari     |
| Viyan Andhika                      | AX7  | 19  |      350.000 | 2026-01-02 | 2026-01 | 00012        | Transfer   | Online | Ari     |
| Tio Fani                           | AX8  | 6   |      300.000 | 2026-01-02 | 2026-01 | 00014        | Transfer   | Online | Ari     |
| Bambang Supriyadi                  | C7   | 38  |      250.000 | 2026-01-05 | 2026-01 | 00017        | VA         | Online | Riska   |
| Joni                               | AX7  | 23  |      350.000 | 2026-01-05 | 2026-01 | 00018        | VA         | Online | Riska   |
| Herman                             | DD3  | 10  |      275.000 | 2026-01-05 | 2026-01 | 00019        | VA         | Online | Riska   |
| Silviana Victoria                  | DD1  | 16  |      275.000 | 2026-01-05 | 2026-01 | 00020        | VA         | Online | Riska   |
| Kartika Puspa                      | AX7  | 8   |      350.000 | 2026-01-05 | 2026-01 | 00021        | VA         | Online | Riska   |
| Multi Infiniti Pro                 | JA   | 8   |      300.000 | 2026-01-05 | 2026-01 | 00022        | VA         | Online | Riska   |
| Ajeng                              | DD1  | 17  |      275.000 | 2026-01-05 | 2026-01 | 00023        | VA         | Online | Riska   |
| Felix Hadianto                     | AX5  | 76  |      200.000 | 2026-01-05 | 2026-01 | 00024        | VA         | Online | Riska   |
| Kartika Melanda                    | AX6  | 27  |      350.000 | 2026-01-05 | 2026-01 | 00025        | VA         | Online | Riska   |
| Noviani Harianto                   | C11  | 10  |      250.000 | 2026-01-05 | 2026-01 | 00026        | VA         | Online | Riska   |
| Giffari Lestionanda                | B8   | 27  |      300.000 | 2026-01-05 | 2026-01 | 00027        | VA         | Online | Riska   |
| Lista Akdriaty                     | C3   | 7   |      250.000 | 2026-01-05 | 2026-01 | 00028        | VA         | Online | Riska   |
| Selviana                           | AX6  | 12  |      250.000 | 2026-01-05 | 2026-01 | 00029        | VA         | Online | Riska   |
| Rita                               | B10  | 21  |      300.000 | 2026-01-05 | 2026-01 | 00031        | VA         | Online | Riska   |
| Friska                             | AX5  | 57  |      250.000 | 2026-01-05 | 2026-01 | 00033        | Tunai      | Loket  | Riska   |
| Andini Laurensia                   | C2   | 22  |      250.000 | 2026-01-05 | 2026-01 | 00036        | Transfer   | Online | Riska   |
| Luman                              | AX7  | 37  |      350.000 | 2026-01-05 | 2026-01 | 00038        | Transfer   | Online | Riska   |
| Sonya Elia Chandra                 | AX5  | 20  |      200.000 | 2026-01-05 | 2026-01 | 00039        | Transfer   | Online | Riska   |
| Denny                              | AX3  | 91  |      200.000 | 2026-01-05 | 2026-01 | 00040        | Transfer   | Online | Riska   |
| Wirya Dharmawan                    | AX3  | 71  |      200.000 | 2026-01-05 | 2026-01 | 00041        | Transfer   | Online | Riska   |
| Stanley / Dompet anak Bangsa       | AX5  | 23  |      250.000 | 2026-01-05 | 2026-01 | 00042        | Transfer   | Online | Riska   |
| Evi / GBI                          | JA   | 1   |      300.000 | 2026-01-05 | 2026-01 | 00043        | Transfer   | Online | Riska   |
| Siti Suprapti                      | AX2  | 8&9 |      500.000 | 2026-01-05 | 2026-01 | 00048        | Transfer   | Online | Riska   |
| Ferry Sofianto                     | DD2  | 12  |      275.000 | 2026-01-06 | 2026-01 | 00050        | VA         | Online | Riska   |
| Eriyono Budi                       | AX3  | 69  |      200.000 | 2026-01-06 | 2026-01 | 00051        | VA         | Online | Riska   |
| Drya Nursanti                      | AX5  | 72  |      200.000 | 2026-01-06 | 2026-01 | 00052        | VA         | Online | Riska   |
| Charlie Chandra                    | AX5  | 38  |      200.000 | 2026-01-06 | 2026-01 | 00053        | VA         | Online | Riska   |
| Melina                             | AX8  | 36  |      300.000 | 2026-01-06 | 2026-01 | 00054        | VA         | Online | Riska   |
| Linda Wati                         | B10  | 39  |      300.000 | 2026-01-06 | 2026-01 | 00055        | VA         | Online | Riska   |
| Hanindya Puspita                   | JA   | 5   |      300.000 | 2026-01-06 | 2026-01 | 00057        | VA         | Online | Riska   |
| Shafa Salsabila                    | AM2  | 18  |      300.000 | 2026-01-06 | 2026-01 | 00058        | VA         | Online | Riska   |
| Seftiningtyas                      | AX3  | 17  |      200.000 | 2026-01-06 | 2026-01 | 00059        | VA         | Online | Riska   |
| Yelsikasari                        | B9   | 60  |      300.000 | 2026-01-06 | 2026-01 | 00060        | VA         | Online | Riska   |
| Yuki Erik                          | B10  | 8   |      300.000 | 2026-01-06 | 2026-01 | 00061        | VA         | Online | Riska   |
| Chandra                            | AX6  | 19  |      350.000 | 2026-01-06 | 2026-01 | 00062        | VA         | Online | Riska   |
| Endah Siwi                         | C5   | 10  |      250.000 | 2026-01-06 | 2026-01 | 00063        | VA         | Online | Riska   |
| Jonathan Surya                     | B9   | 79  |      300.000 | 2026-01-06 | 2025-09 | 00065        | Transfer   | Online | Riska   |
| Evi Yulianti                       | AX3  | 35  |      200.000 | 2026-01-06 | 2026-01 | 00066        | Transfer   | Online | Riska   |
| Yusron Fauzi                       | C8   | 12  |      250.000 | 2026-01-06 | 2026-01 | 00069        | Tunai      | Loket  | Riska   |
| Verawati Wijaya                    | AX5  | 18  |      200.000 | 2026-01-07 | 2026-01 | 00070        | VA         | Online | Riska   |
| Deri Yatno                         | AX7  | 29  |      350.000 | 2026-01-07 | 2026-01 | 00073        | VA         | Online | Riska   |
| Elly Swanti                        | C1   | 11  |      250.000 | 2026-01-07 | 2026-01 | 00075        | VA         | Online | Riska   |
| Noviana                            | AX7  | 33  |      350.000 | 2026-01-07 | 2026-01 | 00076        | VA         | Online | Riska   |
| Imas Rahayu Ningsih                | AX8  | 25  |      300.000 | 2026-01-07 | 2026-01 | 00077        | VA         | Online | Riska   |
| Dedi Sugiarto                      | DD1  | 19  |      275.000 | 2026-01-07 | 2026-01 | 00078        | VA         | Online | Riska   |
| Regina Adytia                      | AX8  | 3   |      300.000 | 2026-01-07 | 2026-01 | 00079        | VA         | Online | Riska   |
| Mok Nyian Djin                     | AX7  | 35  |      350.000 | 2026-01-07 | 2026-01 | 00082        | Transfer   | Online | Riska   |
| Andri Mustakim                     | AX5  | 5   |      250.000 | 2026-01-07 | 2026-01 | 00083        | Transfer   | Online | Riska   |
| Abdul Azis Saifuddin               | C11  | 8   |      250.000 | 2026-01-07 | 2026-01 | 00084        | Transfer   | Online | Riska   |
| Jhon Delon                         | AX5  | 78  |      200.000 | 2026-01-07 | 2026-01 | 00085        | Tunai      | Loket  | Riska   |
| Hans Jakal                         | C1   | 12  |      250.000 | 2026-01-07 | 2026-01 | 00086        | Tunai      | Loket  | Riska   |
| Devi Febrian                       | DD1  | 18  |      275.000 | 2026-01-08 | 2026-01 | 00088        | VA         | Online | Riska   |
| Ervina                             | AM1  | 3   |      300.000 | 2026-01-08 | 2026-01 | 00089        | VA         | Online | Riska   |
| Kristianto                         | JA   | 2   |      300.000 | 2026-01-08 | 2026-01 | 00090        | VA         | Online | Riska   |
| Rika                               | AM2  | 10  |      300.000 | 2026-01-08 | 2026-01 | 00092        | VA         | Online | Riska   |
| Albertus Chandra                   | AX8  | 60  |      300.000 | 2026-01-08 | 2026-01 | 00093        | VA         | Online | Riska   |
| Reza Satria                        | AX6  | 66  |      250.000 | 2026-01-08 | 2026-01 | 00095        | Transfer   | Online | Riska   |
| Lisa Loandha                       | C11  | 20  |      250.000 | 2026-01-08 | 2026-01 | 00096        | Transfer   | Online | Riska   |
| Henni Johanes                      | AX5  | 59  |      250.000 | 2026-01-08 | 2026-01 | 00097        | Transfer   | Online | Riska   |
| Septiany Ganescya                  | C7   | 66  |      250.000 | 2026-01-08 | 2026-01 | 00098        | Transfer   | Online | Riska   |
| Yenni                              | C5   | 12  |      250.000 | 2026-01-09 | 2026-01 | 00100        | VA         | Online | Riska   |
| Linda Apriani                      | AX5  | 70  |      200.000 | 2026-01-09 | 2026-01 | 00101        | VA         | Online | Riska   |
| Rinaldy Tarmadi                    | AX5  | 39  |      250.000 | 2026-01-09 | 2026-01 | 00102        | VA         | Online | Riska   |
| Ervina                             | AM1  | 5   |      300.000 | 2026-01-09 | 2026-01 | 00103        | VA         | Online | Riska   |
| Andhi Indarto                      | E7   | 12  |      300.000 | 2026-01-09 | 2026-01 | 00104        | Transfer   | Online | Riska   |
| Sapto Rini Rushapsari              | AX6  | 7   |      350.000 | 2026-01-09 | 2026-01 | 00105        | Transfer   | Online | Riska   |
| Eddy                               | AX8  | 53  |      300.000 | 2026-01-09 | 2026-01 | 00106        | Transfer   | Online | Riska   |
| Leo Baratanata                     | DD1  | 15  |      275.000 | 2026-01-12 | 2026-01 | 00107        | VA         | Online | Riska   |
| Rosia Daeli                        | C6   | 27  |      250.000 | 2026-01-12 | 2026-01 | 00108        | VA         | Online | Riska   |
| Anita Carolina                     | B10  | 32  |      300.000 | 2026-01-12 | 2026-01 | 00109        | VA         | Online | Riska   |
| Anastasia Dame                     | AX7  | 7   |      350.000 | 2026-01-12 | 2026-01 | 00111        | VA         | Online | Riska   |
| Friciel Yael                       | B8   | 79  |      300.000 | 2026-01-12 | 2026-01 | 00112        | VA         | Online | Riska   |
| Julio Andrian                      | AX3  | 29  |      200.000 | 2026-01-12 | 2026-01 | 00113        | VA         | Online | Riska   |
| Else                               | DD1  | 2   |      275.000 | 2026-01-12 | 2026-01 | 00115        | VA         | Online | Riska   |
| Kuswanto                           | AM2  | 19  |      300.000 | 2026-01-12 | 2026-01 | 00116        | VA         | Online | Riska   |
| Mohammad Syukron                   | C6   | 19  |      250.000 | 2026-01-12 | 2026-01 | 00117        | VA         | Online | Riska   |
| Johanna Hermawan                   | DD2  | 3   |      275.000 | 2026-01-12 | 2026-01 | 00118        | Transfer   | Online | Riska   |
| Jangkau Lintas Nusantara           | E7   | 50  |      300.000 | 2026-01-12 | 2026-01 | 00120        | Transfer   | Online | Riska   |
| Darwin                             | AX6  | 25  |      350.000 | 2026-01-12 | 2026-01 | 00121        | Transfer   | Online | Riska   |
| Yuliana                            | C6   | 31  |      250.000 | 2026-01-12 | 2026-01 | 00123        | Transfer   | Online | Riska   |
| Herson                             | C7   | 28  |      250.000 | 2026-01-12 | 2025-01 | 00126        | Transfer   | Online | Riska   |
| Herson                             | D8   | 3   |      350.000 | 2026-01-12 | 2026-01 | 00128        | Transfer   | Online | Riska   |
| Herson                             | D8   | 7   |      350.000 | 2026-01-12 | 2026-01 | 00129        | Transfer   | Online | Riska   |
| Hon King Wie                       | C2   | 20  |      250.000 | 2026-01-12 | 2025-11 | 00130        | Transfer   | Online | Riska   |
| Tham Alifin                        | AX5  | 29  |      250.000 | 2026-01-12 | 2026-01 | 00133        | Transfer   | Online | Riska   |
| Tham Alifin                        | AX6  | 6   |      250.000 | 2026-01-12 | 2026-01 | 00134        | Transfer   | Online | Riska   |
| Putri Alvina                       | AX7  | 27  |      350.000 | 2026-01-12 | 2026-01 | 00136        | Transfer   | Online | Riska   |
| Putri Alvina                       | JA   | 18  |      300.000 | 2026-01-12 | 2026-01 | 00137        | Transfer   | Online | Riska   |
| Gwat lie                           | AM1  | 1   |      300.000 | 2026-01-13 | 2026-01 | 00138        | VA         | Online | Riska   |
| Agnes Wiwik                        | B10  | 77  |      300.000 | 2026-01-13 | 2026-01 | 00139        | VA         | Online | Riska   |
| Vivin Rosalia                      | C10  | 2   |      250.000 | 2026-01-13 | 2026-01 | 00140        | VA         | Online | Riska   |
| Nova Diana                         | B10  | 6   |      300.000 | 2026-01-13 | 2026-01 | 00141        | Transfer   | Online | Riska   |
| Aprilly Putri                      | E7   | 26  |      300.000 | 2026-01-13 | 2026-01 | 00143        | Transfer   | Online | Riska   |
| Hendi                              | DD1  | 7   |      275.000 | 2026-01-13 | 2026-01 | 00145        | Transfer   | Online | Riska   |
| Sabeth                             | AX8  | 30  |      300.000 | 2026-01-14 | 2026-01 | 00147        | VA         | Online | Riska   |
| Irwan                              | AX3  | 37  |      200.000 | 2026-01-14 | 2026-01 | 00148        | VA         | Online | Riska   |
| GSJA Abbacare                      | AL2  | 12  |      300.000 | 2026-01-14 | 2026-01 | 00149        | Transfer   | Online | Riska   |
| Iwan Kiswanto                      | B7   | 39  |      300.000 | 2026-01-14 | 2026-01 | 00150        | Transfer   | Online | Riska   |
| Arnold Dwi Putra                   | AX5  | 26  |      200.000 | 2026-01-15 | 2026-01 | 00156        | VA         | Online | Riska   |
| Hanafi                             | C7   | 26  |      250.000 | 2026-01-15 | 2026-01 | 00157        | VA         | Online | Riska   |
| Putri Apriani                      | AX6  | 16  |      250.000 | 2026-01-15 | 2026-01 | 00158        | VA         | Online | Riska   |
| Indomaret Grand Duta 2 / Indomarko | E7   | 27  |      300.000 | 2026-01-15 | 2026-01 | 00159        | Transfer   | Online | Riska   |
| Indomaret Grand Duta 2 / Indomarko | E7   | 28  |      300.000 | 2026-01-15 | 2026-01 | 00160        | Transfer   | Online | Riska   |
| Siti Nurwati                       | AX6  | 17  |      350.000 | 2026-01-19 | 2026-01 | 00164        | VA         | Online | Riska   |
| Agus Setiawan                      | JA   | 7   |      300.000 | 2026-01-19 | 2026-01 | 00165        | VA         | Online | Riska   |
| Purnawati SE                       | AX6  | 9   |      350.000 | 2026-01-19 | 2026-01 | 00166        | VA         | Online | Riska   |
| Sinar Winarni                      | AX7  | 5   |      350.000 | 2026-01-19 | 2026-01 | 00167        | VA         | Online | Riska   |
| Berli                              | AX8  | 28  |      300.000 | 2026-01-20 | 2026-01 | 00170        | Tunai      | Loket  | Riska   |
| Selvy Tania                        | B10  | 10  |      300.000 | 2026-01-20 | 2026-01 | 00171        | VA         | Online | Riska   |
| Yulin                              | AX8  | 39  |      300.000 | 2026-01-20 | 2026-01 | 00172        | VA         | Online | Riska   |
| Firdha                             | B10  | 26  |      300.000 | 2026-01-20 | 2026-01 | 00173        | VA         | Online | Riska   |
| Fransisca Herlin                   | B8   | 35  |      300.000 | 2026-01-20 | 2026-01 | 00174        | VA         | Online | Riska   |
| Dewi Santosari                     | C5   | 9   |      250.000 | 2026-01-20 | 2026-01 | 00175        | VA         | Online | Riska   |
| Etri Septivia                      | B9   | 59  |      300.000 | 2026-01-20 | 2025-11 | 00176        | Transfer   | Online | Riska   |
| Nico Cristian                      | E7   | 8   |      300.000 | 2026-01-21 | 2026-01 | 00178        | VA         | Online | Riska   |
| Muhamad Fajrin                     | D7   | 8   |      350.000 | 2026-01-21 | 2026-01 | 00179        | VA         | Online | Riska   |
| Andika                             | C2   | 23  |      250.000 | 2026-01-21 | 2025-11 | 00182        | Transfer   | Online | Riska   |
| Indomaret Grand Duta 1             | JA   | 10  |      300.000 | 2026-01-21 | 2026-01 | 00184        | Transfer   | Online | Riska   |
| Indomaret Grand Duta 1             | JA   | 11  |      300.000 | 2026-01-21 | 2026-01 | 00185        | Transfer   | Online | Riska   |
| Indrawan                           | AX3  | 39  |      200.000 | 2026-01-21 | 2026-01 | 00186        | VA         | Online | Riska   |
| Dessy                              | AX7  | 55  |      350.000 | 2026-01-21 | 2026-01 | 00187        | VA         | Online | Riska   |
| Tjahyadi Theodora                  | C7   | 56  |      250.000 | 2026-01-22 | 2026-01 | 00190        | Transfer   | Online | Riska   |
| Yani Lestari                       | AX7  | 6   |      350.000 | 2026-01-22 | 2026-01 | 00193        | VA         | Online | Riska   |
| Arlia Anwar                        | B10  | 78  |      300.000 | 2026-01-22 | 2026-01 | 00194        | VA         | Online | Riska   |
| Arlia Anwar                        | AX6  | 31  |      350.000 | 2026-01-22 | 2026-01 | 00195        | VA         | Online | Riska   |
| Betty Heriasati                    | AX5  | 63  |      250.000 | 2026-01-23 | 2026-01 | 00198        | Transfer   | Online | Riska   |
| Denis Sofia Lestari                | AX3  | 55  |      200.000 | 2026-01-24 | 2026-01 | 00202        | VA         | Online | Riska   |
| Siti Fatonah                       | AX5  | 32  |      200.000 | 2026-01-24 | 2026-01 | 00203        | VA         | Online | Riska   |
| Liyanawati                         | AX7  | 16  |      350.000 | 2026-01-24 | 2026-02 | 00205        | Transfer   | Online | Riska   |
| Hendry                             | DD1  | 9   |      275.000 | 2026-01-25 | 2026-01 | 00206        | VA         | Online | Riska   |
| Hadianto                           | C8   | 11  |      250.000 | 2026-01-25 | 2026-01 | 00207        | VA         | Online | Riska   |
| Henry Tulus                        | DD2  | 5   |      275.000 | 2026-01-25 | 2026-01 | 00209        | Transfer   | Online | Riska   |
| Meti Oktavia                       | DD2  | 2   |      275.000 | 2026-01-25 | 2026-02 | 00210        | Transfer   | Online | Riska   |
| Riyandi Susantu                    | C11  | 28  |      250.000 | 2026-01-26 | 2026-01 | 00212        | Transfer   | Loket  | Riska   |
| Juan Ignatius                      | AX3  | 1   |      200.000 | 2026-01-26 | 2026-01 | 00213        | Transfer   | Loket  | Riska   |
| Stephanie Setiawan                 | DD1  | 6   |      275.000 | 2026-01-26 | 2026-01 | 00215        | Transfer   | Loket  | Riska   |
| Herlika                            | AX5  | 35  |      250.000 | 2026-01-26 | 2026-01 | 00217        | VA         | Loket  | Riska   |
| Sugandi                            | D8   | 2   |      350.000 | 2026-01-26 | 2026-01 | 00219        | VA         | Loket  | Riska   |
| Efroy Totok Daryanto               | D8   | 10  |      350.000 | 2026-01-26 | 2026-01 | 00220        | VA         | Loket  | Riska   |
| Handrian Wijaya                    | DD1  | 5   |      275.000 | 2026-01-27 | 2026-02 | 00225        | Transfer   | Loket  | Riska   |
| Jhoni Candra                       | C1   | 6   |      250.000 | 2026-01-27 | 2026-01 | 00226        | Transfer   | Loket  | Riska   |
| Go Wie Guan                        | AX8  | 12  |      300.000 | 2026-01-27 | 2026-01 | 00227        | VA         | Loket  | Riska   |
| Rangga Afyan                       | AX6  | 62  |      250.000 | 2026-01-28 | 2026-01 | 00229        | Transfer   | Loket  | Riska   |
| Mohamad Yusuf                      | B8   | 81  |      300.000 | 2026-01-28 | 2026-01 | 00231        | Transfer   | Loket  | Riska   |
| Stefanus Andre                     | AX9  | 50  |      300.000 | 2026-01-28 | 2026-02 | 00232        | Transfer   | Loket  | Riska   |
| Rohani Susi Herlin                 | B9   | 10  |      300.000 | 2026-01-28 | 2026-01 | 00233        | Transfer   | Loket  | Riska   |
| Nesa Apriari                       | B8   | 67  |      300.000 | 2026-01-28 | 2026-01 | 00234        | VA         | Loket  | Riska   |
| Dilla Ardila                       | B10  | 25  |      300.000 | 2026-01-28 | 2026-01 | 00235        | VA         | Loket  | Riska   |
| Sahrial Lubis                      | C2   | 27  |      250.000 | 2026-01-28 | 2026-01 | 00236        | Tunai      | Loket  | Riska   |
| Hani Yesy                          | C6   | 33  |      250.000 | 2026-01-29 | 2026-01 | 00237        | Transfer   | Online | Riska   |
| Vilani Osa Se                      | AX5  | 65  |      250.000 | 2026-01-29 | 2026-01 | 00238        | Transfer   | Online | Riska   |
| Rolis                              | AX3  | 51  |      200.000 | 2026-01-29 | 2025-09 | 00239        | Transfer   | Online | Riska   |
| Ameng                              | C11  | 18  |      250.000 | 2026-01-29 | 2026-01 | 00240        | Transfer   | Online | Riska   |
| Fransisca Natalia                  | C11  | 16  |      250.000 | 2026-01-29 | 2026-01 | 00242        | VA         | Online | Riska   |
| Badai Rosamudra                    | AX5  | 56  |      200.000 | 2026-01-29 | 2026-01 | 00243        | VA         | Online | Riska   |
| Ediana Sigalingging                | AM2  | 1   |      300.000 | 2026-01-30 | 2026-01 | 00244        | Tunai      | Loket  | Riska   |
| Ediana Sigalingging                | AM2  | 2   |      300.000 | 2026-01-30 | 2026-01 | 00245        | Tunai      | Loket  | Riska   |
| Gusnita                            | B9   | 35  |      300.000 | 2026-01-30 | 2026-01 | 00247        | Transfer   | Online | Riska   |
| Riecson Wullur                     | JA   | 23  |      300.000 | 2026-01-30 | 2026-01 | 00249        | Transfer   | Online | Riska   |
| Riecson Wullur                     | DD1  | 20  |      275.000 | 2026-01-30 | 2026-01 | 00250        | Transfer   | Online | Riska   |
| Novita Anggraeni                   | AX5  | 96  |      200.000 | 2026-01-30 | 2026-01 | 00251        | VA         | Online | Riska   |
| Novita Anggraeni                   | AM2  | 12  |      300.000 | 2026-01-30 | 2026-01 | 00252        | VA         | Online | Riska   |
| Ronal Yahya                        | C1   | 2   |      250.000 | 2026-01-30 | 2026-01 | 00253        | VA         | Online | Riska   |
| Ernest Kurniawan                   | DD2  | 8   |      275.000 | 2026-01-31 | 2026-01 | 00254        | Transfer   | Online | Riska   |
| Dalyano Kayambo                    | AX3  | 81  |      200.000 | 2026-01-31 | 2025-06 | 00255        | Transfer   | Online | Riska   |
| Purnama Sari Djaja                 | E7   | 15  |      300.000 | 2026-01-31 | 2026-01 | 00257        | Transfer   | Online | Riska   |
| Purnama Sari Djaja                 | E7   | 29  |      300.000 | 2026-01-31 | 2026-01 | 00258        | Transfer   | Online | Riska   |
| Mugi Asih                          | AX5  | 9   |      250.000 | 2026-01-31 | 2026-01 | 00259        | Transfer   | Online | Riska   |
| Meliana                            | C7   | 58  |      250.000 | 2026-01-31 | 2026-02 | 00260        | Transfer   | Online | Riska   |
| Arie Wibowo                        | AM1  | 7   |      300.000 | 2026-01-31 | 2026-01 | 00261        | Transfer   | Online | Riska   |
| Sukardi                            | AX5  | 21  |      250.000 | 2026-01-31 | 2025-11 | 00262        | Transfer   | Online | Riska   |
| Tanu Wijaya                        | DD2  | 7   |      275.000 | 2026-01-31 | 2026-02 | 00263        | Transfer   | Online | Riska   |

## Februari 2026

| Nama                         | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC    | Petugas |
| ---------------------------- | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ------ | ------- |
| Budhi Mulyadi                | DD3  | 3   |      275.000 | 2026-02-01 | 2026-02 | 00265        | Transfer   | Online | Riska   |
| Rezky Iriansyah              | B9   | 78  |      300.000 | 2026-02-01 | 2026-02 | 00266        | Transfer   | Online | Riska   |
| Viyan Andhika                | AX7  | 19  |      350.000 | 2026-02-01 | 2026-02 | 00267        | Transfer   | Online | Riska   |
| Andini Laurensia             | C2   | 22  |      250.000 | 2026-02-01 | 2026-02 | 00268        | Transfer   | Online | Riska   |
| Evi Yulianti                 | AX3  | 35  |      200.000 | 2026-02-01 | 2026-02 | 00269        | Transfer   | Online | Riska   |
| Selvy                        | AX8  | 7   |      300.000 | 2026-02-01 | 2026-02 | 00271        | Transfer   | Online | Riska   |
| Jhon Delon                   | AX5  | 78  |      200.000 | 2026-02-02 | 2026-02 | 00264        | Tunai      | Loket  | Riska   |
| Lucky Suryana                | B9   | 17  |      300.000 | 2026-02-02 | 2025-09 | 00272        | Tunai      | Loket  | Riska   |
| Melisa Apriyani              | AX5  | 80  |      200.000 | 2026-02-02 | 2026-02 | 00274        | Transfer   | Online | Riska   |
| Septiany Ganescya            | C7   | 66  |      250.000 | 2026-02-02 | 2026-02 | 00275        | Transfer   | Online | Riska   |
| M Alifian Khairi             | C9   | 7   |      250.000 | 2026-02-02 | 2026-02 | 00276        | Tunai      | Loket  | Riska   |
| Tio Fani                     | AX8  | 6   |      300.000 | 2026-02-02 | 2026-02 | 00277        | Transfer   | Online | Riska   |
| Iwan Kiswanto                | B7   | 39  |      300.000 | 2026-02-02 | 2026-02 | 00279        | Transfer   | Online | Riska   |
| Ordie                        | B8   | 10  |      300.000 | 2026-02-02 | 2026-02 | 00280        | Transfer   | Online | Riska   |
| Wulan Januri                 | C6   | 37  |      250.000 | 2026-02-02 | 2026-02 | 00281        | Transfer   | Online | Riska   |
| Novriyansyah Karna           | AM1  | 11  |      300.000 | 2026-02-02 | 2026-01 | 00282        | Transfer   | Online | Riska   |
| Henni Johannes               | AX5  | 59  |      250.000 | 2026-02-02 | 2026-02 | 00283        | Transfer   | Online | Riska   |
| Yudian Fantoni               | B9   | 36  |      300.000 | 2026-02-03 | 2026-01 | 00284        | Tunai      | Loket  | Riska   |
| Yusron Fauzi                 | C8   | 12  |      250.000 | 2026-02-03 | 2026-02 | 00285        | Tunai      | Loket  | Riska   |
| Wirya Dharmawan              | AX3  | 71  |      200.000 | 2026-02-03 | 2026-02 | 00286        | Transfer   | Online | Riska   |
| Konnie Gunawan               | B9   | 58  |      300.000 | 2026-02-03 | 2026-01 | 00289        | Transfer   | Online | Riska   |
| Lilih Yuliani                | AX5  | 36  |      200.000 | 2026-02-03 | 2026-01 | 00290        | Transfer   | Online | Riska   |
| Evi / GBI                    | JA   | 1   |      300.000 | 2026-02-03 | 2026-02 | 00291        | Transfer   | Online | Riska   |
| Kristiandi                   | B9   | 56  |      300.000 | 2026-02-03 | 2026-01 | 00292        | Transfer   | Online | Riska   |
| Devi Febrian                 | DD1  | 18  |      275.000 | 2026-02-03 | 2026-02 | 00295        | Transfer   | Online | Riska   |
| Stanley / Dompet anak bangsa | AX5  | 23  |      250.000 | 2026-02-03 | 2026-02 | 00297        | Transfer   | Online | Riska   |
| Herman                       | DD3  | 10  |      275.000 | 2026-02-03 | 2026-02 | 00298        | VA         | Online | Riska   |
| Bambang Supriyadi            | C7   | 38  |      250.000 | 2026-02-03 | 2026-02 | 00299        | VA         | Online | Riska   |
| Kartika Puspa                | AX7  | 8   |      350.000 | 2026-02-03 | 2026-02 | 00300        | VA         | Online | Riska   |
| Verawati Wijaya              | AX5  | 18  |      200.000 | 2026-02-03 | 2026-02 | 00301        | VA         | Online | Riska   |
| Linda Wati                   | B10  | 39  |      300.000 | 2026-02-03 | 2026-02 | 00302        | VA         | Online | Riska   |
| Selviana                     | AX6  | 12  |      250.000 | 2026-02-03 | 2026-02 | 00303        | VA         | Online | Riska   |
| Ferry Sofianto               | DD2  | 12  |      275.000 | 2026-02-03 | 2026-02 | 00304        | VA         | Online | Riska   |
| Chandra                      | AX6  | 19  |      350.000 | 2026-02-03 | 2026-02 | 00305        | VA         | Online | Riska   |
| Hanafi                       | C7   | 26  |      250.000 | 2026-02-03 | 2026-02 | 00306        | VA         | Online | Riska   |
| Mohammad Syukron             | C6   | 19  |      250.000 | 2026-02-03 | 2026-02 | 00307        | VA         | Online | Riska   |
| Silviana Victoria            | DD1  | 16  |      275.000 | 2026-02-03 | 2026-02 | 00308        | VA         | Online | Riska   |
| Kartika Melanda              | AX6  | 27  |      350.000 | 2026-02-03 | 2026-02 | 00309        | VA         | Online | Riska   |
| Deri Yatno                   | AX7  | 29  |      350.000 | 2026-02-03 | 2026-02 | 00310        | VA         | Online | Riska   |
| Muhammad Fachdillah          | AX6  | 70  |      250.000 | 2026-02-03 | 2026-02 | 00312        | VA         | Online | Riska   |
| Noviana                      | AX7  | 33  |      350.000 | 2026-02-03 | 2026-02 | 00313        | VA         | Online | Riska   |
| Noviani Harianto             | C11  | 10  |      250.000 | 2026-02-03 | 2026-02 | 00314        | VA         | Online | Riska   |
| Drya Nursanti                | AX5  | 72  |      200.000 | 2026-02-03 | 2026-02 | 00316        | VA         | Online | Riska   |
| Friciel Yael                 | B8   | 79  |      300.000 | 2026-02-03 | 2026-02 | 00317        | VA         | Online | Riska   |
| Melina                       | AX8  | 36  |      300.000 | 2026-02-03 | 2026-02 | 00318        | VA         | Online | Riska   |
| Dedi Sugiarto                | DD1  | 19  |      275.000 | 2026-02-03 | 2026-02 | 00319        | VA         | Online | Riska   |
| Rita                         | B10  | 21  |      300.000 | 2026-02-03 | 2026-02 | 00320        | VA         | Online | Riska   |
| Purnawati SE                 | AX6  | 9   |      350.000 | 2026-02-03 | 2026-02 | 00321        | VA         | Online | Riska   |
| Rossela                      | C6   | 53  |      250.000 | 2026-02-04 | 2025-11 | 00324        | Transfer   | Online | Riska   |
| Nove Diana                   | B10  | 6   |      300.000 | 2026-02-04 | 2026-02 | 00325        | Transfer   | Online | Riska   |
| Sinta Dewi                   | AX5  | 33  |      250.000 | 2026-02-04 | 2026-02 | 00326        | Transfer   | Online | Riska   |
| Pramadasyah                  | B9   | 32  |      300.000 | 2026-02-04 | 2026-01 | 00327        | Transfer   | Online | Riska   |
| Denny                        | AX3  | 91  |      200.000 | 2026-02-04 | 2026-02 | 00329        | Transfer   | Online | Riska   |
| Tri Septiyanti               | E7   | 7   |      300.000 | 2026-02-04 | 2025-11 | 00330        | Transfer   | Online | Riska   |
| Mohamad Arif                 | E7   | 18  |      300.000 | 2026-02-04 | 2026-02 | 00331        | VA         | Online | Riska   |
| Charlie Chandra              | AX5  | 38  |      200.000 | 2026-02-04 | 2026-02 | 00332        | VA         | Online | Riska   |
| Giffari Lestionanda          | B8   | 27  |      300.000 | 2026-02-04 | 2026-02 | 00333        | VA         | Online | Riska   |
| Joni                         | AX7  | 23  |      350.000 | 2026-02-04 | 2026-02 | 00335        | VA         | Online | Riska   |
| Irwan                        | AX3  | 37  |      200.000 | 2026-02-04 | 2026-02 | 00336        | VA         | Online | Riska   |
| Multi Infiniti Pro           | JA   | 8   |      300.000 | 2026-02-04 | 2026-02 | 00338        | VA         | Online | Riska   |
| Hanindya Puspita             | JA   | 5   |      300.000 | 2026-02-04 | 2026-02 | 00339        | VA         | Online | Riska   |
| Friska                       | AX5  | 57  |      250.000 | 2026-02-05 | 2026-02 | 00340        | Tunai      | Loket  | Riska   |
| Lusiana                      | AX8  | 38  |      300.000 | 2026-02-05 | 2026-02 | 00343        | VA         | Online | Riska   |
| Rully Angga                  | AX7  | 12  |      350.000 | 2026-02-05 | 2026-02 | 00344        | VA         | Online | Riska   |
| Regina Adytia                | AX8  | 3   |      300.000 | 2026-02-05 | 2026-02 | 00345        | VA         | Online | Riska   |
| Imas Rahayu Ningsih          | AX8  | 25  |      300.000 | 2026-02-05 | 2026-02 | 00346        | VA         | Online | Riska   |
| Linda Apriani                | AX5  | 70  |      200.000 | 2026-02-05 | 2026-02 | 00347        | VA         | Online | Riska   |
| Ajeng                        | DD1  | 17  |      275.000 | 2026-02-05 | 2026-02 | 00348        | VA         | Online | Riska   |
| Vina Karya                   | AL2  | 11  |      300.000 | 2026-02-06 | 2026-01 | 00351        | Tunai      | Loket  | Riska   |
| Andri Mustakim               | AX5  | 5   |      250.000 | 2026-02-06 | 2026-02 | 00352        | Transfer   | Online | Riska   |
| Yuliana                      | C6   | 31  |      250.000 | 2026-02-06 | 2026-02 | 00353        | Transfer   | Online | Riska   |
| Yen Min                      | E7   | 37  |      300.000 | 2026-02-06 | 2026-01 | 00354        | Transfer   | Online | Riska   |
| Hans Jakal                   | C1   | 12  |      250.000 | 2026-02-06 | 2026-02 | 00356        | Tunai      | Loket  | Riska   |
| Eriyono Budi                 | AX3  | 69  |      200.000 | 2026-02-06 | 2026-02 | 00357        | VA         | Online | Riska   |
| Felix Hadianto               | AX5  | 76  |      200.000 | 2026-02-06 | 2026-02 | 00358        | VA         | Online | Riska   |
| Lista Akdriaty               | C3   | 7   |      250.000 | 2026-02-06 | 2026-02 | 00359        | VA         | Online | Riska   |
| Sapto Rini Rushapsari        | AX6  | 7   |      350.000 | 2026-02-07 | 2026-02 | 00360        | Transfer   | Loket  | Riska   |
| Luman                        | AX7  | 37  |      350.000 | 2026-02-07 | 2026-02 | 00361        | Transfer   | Loket  | Riska   |
| Andhi Indarto                | E7   | 12  |      300.000 | 2026-02-08 | 2026-02 | 00362        | Transfer   | Online | Riska   |
| Siti Suprapti                | AX2  | 8&9 |      500.000 | 2026-02-08 | 2026-02 | 00363        | Transfer   | Online | Riska   |
| Yuki Erik                    | B10  | 8   |      300.000 | 2026-02-09 | 2026-02 | 00365        | VA         | Online | Riska   |
| Mok Nyian Djin               | AX7  | 35  |      350.000 | 2026-02-09 | 2026-02 | 00366        | Transfer   | Online | Riska   |
| Eddy                         | AX8  | 53  |      300.000 | 2026-02-09 | 2026-02 | 00367        | Transfer   | Online | Riska   |
| Lisa Loandha                 | C11  | 20  |      250.000 | 2026-02-09 | 2026-02 | 00368        | Transfer   | Online | Riska   |
| Stefanus                     | C3   | 9   |      250.000 | 2026-02-09 | 2026-01 | 00369        | Transfer   | Online | Riska   |
| Lusia Tungadi                | D8   | 11  |      350.000 | 2026-02-09 | 2026-01 | 00372        | Transfer   | Online | Riska   |
| Jangkau Lintas Nusantara     | E7   | 50  |      300.000 | 2026-02-09 | 2026-02 | 00373        | Transfer   | Online | Riska   |
| Sabeth                       | AX8  | 30  |      300.000 | 2026-02-09 | 2026-02 | 00375        | VA         | Online | Riska   |
| Ervina                       | AM1  | 3   |      300.000 | 2026-02-09 | 2026-02 | 00376        | VA         | Online | Riska   |
| Ervina                       | AM1  | 5   |      300.000 | 2026-02-09 | 2026-02 | 00377        | VA         | Online | Riska   |
| Euis Tuty                    | AM2  | 6   |      300.000 | 2026-02-10 | 2026-01 | 00378        | Tunai      | Loket  | Riska   |
| Johanna Hermawan             | DD2  | 3   |      275.000 | 2026-02-10 | 2026-02 | 00380        | Transfer   | Online | Riska   |
| Sonya Elia Chandra           | AX5  | 20  |      200.000 | 2026-02-10 | 2026-02 | 00381        | Transfer   | Online | Riska   |
| Abdul Azis Saifuddin         | C11  | 8   |      250.000 | 2026-02-10 | 2026-02 | 00382        | Transfer   | Online | Riska   |
| Darwin                       | AX6  | 25  |      350.000 | 2026-02-10 | 2026-02 | 00383        | Transfer   | Online | Riska   |
| Achmad Yudha                 | AX5  | 11  |      250.000 | 2026-02-10 | 2026-01 | 00385        | Transfer   | Online | Riska   |
| Herlika                      | AX5  | 35  |      250.000 | 2026-02-10 | 2026-02 | 00387        | VA         | Online | Riska   |
| Kristianto                   | JA   | 2   |      300.000 | 2026-02-10 | 2026-02 | 00388        | VA         | Online | Riska   |
| Agus Setiawan                | JA   | 7   |      300.000 | 2026-02-10 | 2026-02 | 00389        | VA         | Online | Riska   |
| Agnes Wiwik                  | B10  | 77  |      300.000 | 2026-02-10 | 2026-02 | 00390        | VA         | Online | Riska   |
| Else                         | DD1  | 2   |      275.000 | 2026-02-10 | 2026-02 | 00392        | VA         | Online | Riska   |
| Jaya Iskandar                | C9   | 8   |      250.000 | 2026-02-11 | 2025-01 | 00393        | Tunai      | Loket  | Riska   |
| Herson                       | AX9  | 38  |      300.000 | 2026-02-11 | 2026-01 | 00397        | Transfer   | Online | Riska   |
| Herson                       | C6   | 55  |      250.000 | 2026-02-11 | 2026-01 | 00398        | Transfer   | Online | Riska   |
| Herson                       | C7   | 28  |      250.000 | 2026-02-11 | 2026-02 | 00399        | Transfer   | Online | Riska   |
| Herson                       | AL2  | 5   |      300.000 | 2026-02-11 | 2026-01 | 00400        | Transfer   | Online | Riska   |
| Herson                       | D8   | 3   |      350.000 | 2026-02-11 | 2026-02 | 00401        | Transfer   | Online | Riska   |
| Herson                       | D8   | 7   |      350.000 | 2026-02-11 | 2026-02 | 00402        | Transfer   | Online | Riska   |
| Anastasia Dame               | AX7  | 7   |      350.000 | 2026-02-11 | 2026-02 | 00403        | VA         | Online | Riska   |
| Kuswanto                     | AM2  | 19  |      300.000 | 2026-02-11 | 2026-02 | 00404        | VA         | Online | Riska   |
| Nicholas Widjaja             | DD1  | 7   |      275.000 | 2026-02-11 | 2026-02 | 00405        | VA         | Online | Riska   |
| Hendry                       | DD1  | 9   |      275.000 | 2026-02-11 | 2026-02 | 00406        | VA         | Online | Riska   |
| Albertus Chandra             | AX8  | 60  |      300.000 | 2026-02-11 | 2026-02 | 00407        | VA         | Online | Riska   |
| Nani Junaeni                 | AX8  | 19  |      300.000 | 2026-02-12 | 2026-01 | 00409        | Transfer   | Online | Riska   |
| Anita Carolina               | B10  | 32  |      300.000 | 2026-02-12 | 2026-02 | 00414        | VA         | Online | Riska   |
| Seftiningtyas                | AX3  | 17  |      200.000 | 2026-02-12 | 2026-02 | 00416        | VA         | Online | Riska   |
| Siti Nurwati                 | AX6  | 17  |      350.000 | 2026-02-12 | 2026-02 | 00417        | VA         | Online | Riska   |
| GSJA Abbacare                | AL2  | 12  |      300.000 | 2026-02-13 | 2026-02 | 00420        | Transfer   | Online | Riska   |
| Than Alifin                  | AX6  | 6   |      250.000 | 2026-02-13 | 2026-02 | 00421        | Transfer   | Online | Riska   |
| Than Alifin                  | AX5  | 29  |      250.000 | 2026-02-13 | 2026-02 | 00422        | Transfer   | Online | Riska   |
| Gwat Lie                     | AM1  | 1   |      300.000 | 2026-02-13 | 2026-02 | 00424        | VA         | Online | Riska   |
| Dewi Santosari               | C5   | 9   |      250.000 | 2026-02-13 | 2026-02 | 00426        | VA         | Online | Riska   |
| Sugandi                      | D8   | 2   |      350.000 | 2026-02-13 | 2026-02 | 00427        | VA         | Online | Riska   |
| Rinaldy Tarmadi              | AX5  | 39  |      250.000 | 2026-02-13 | 2026-02 | 00428        | VA         | Online | Riska   |
| Rika                         | AM2  | 10  |      300.000 | 2026-02-14 | 2026-02 | 00429        | VA         | Loket  | Riska   |
| Julio Andrian                | AX3  | 29  |      200.000 | 2026-02-16 | 2026-02 | 00431        | VA         | Online | Riska   |
| Vivin Rosalia                | C10  | 2   |      250.000 | 2026-02-16 | 2026-02 | 00432        | VA         | Online | Riska   |
| IDM 2 / Indomarco            | E7   | 27  |      300.000 | 2026-02-16 | 2026-02 | 00433        | Transfer   | Online | Riska   |
| IDM 2 / Indomarco            | E7   | 28  |      300.000 | 2026-02-16 | 2026-02 | 00434        | Transfer   | Online | Riska   |
| Elly Swanti                  | C1   | 11  |      250.000 | 2026-02-18 | 2026-02 | 00435        | VA         | Online | Riska   |
| Arnold Dwi Putra             | AX5  | 26  |      200.000 | 2026-02-18 | 2026-02 | 00436        | VA         | Online | Riska   |
| Shafa Salsabila              | AM2  | 18  |      300.000 | 2026-02-19 | 2026-02 | 00439        | VA         | Online | Arianto |
| Fransisca                    | B8   | 35  |      300.000 | 2026-02-19 | 2026-02 | 00440        | VA         | Online | Arianto |
| Sinar Winarni                | AX7  | 5   |      350.000 | 2026-02-19 | 2026-02 | 00441        | VA         | Online | Arianto |
| Berli                        | AX8  | 28  |      300.000 | 2026-02-20 | 2026-02 | 00442        | Tunai      | Loket  | Arianto |
| Indrawan                     | AX3  | 39  |      200.000 | 2026-02-20 | 2026-02 | 00443        | VA         | Loket  | Riska   |
| Efroy Totok Daryanto         | D8   | 10  |      350.000 | 2026-02-21 | 2026-02 | 00445        | VA         | Online | Riska   |
| Sutiroh                      | E7   | 2   |      300.000 | 2026-02-21 | 2026-02 | 00446        | Transfer   | Online | Riska   |
| Betty Heriasati              | AX5  | 63  |      250.000 | 2026-02-23 | 2026-02 | 00449        | Transfer   | Online | Riska   |
| Yani Lestari                 | AX7  | 6   |      350.000 | 2026-02-23 | 2026-02 | 00450        | VA         | Online | Riska   |
| Nani Junaeni                 | AX8  | 19  |      300.000 | 2026-02-24 | 2026-02 | 00452        | Transfer   | Online | Riska   |
| Tjoendro Putjoeng            | E7   | 10  |      300.000 | 2026-02-24 | 2026-01 | 00453        | Transfer   | Online | Riska   |
| Tjahyadi Theodora            | C7   | 56  |      250.000 | 2026-02-24 | 2026-02 | 00454        | Transfer   | Online | Riska   |
| Siti Fatonah                 | AX5  | 32  |      200.000 | 2026-02-24 | 2026-02 | 00456        | VA         | Online | Riska   |
| Rosia Daeli                  | C6   | 27  |      250.000 | 2026-02-24 | 2026-02 | 00457        | VA         | Online | Riska   |
| Hadianto                     | C8   | 11  |      250.000 | 2026-02-24 | 2026-02 | 00458        | VA         | Online | Riska   |
| Stephanie Setiawan           | DD1  | 6   |      275.000 | 2026-02-25 | 2026-02 | 00461        | Transfer   | Online | Riska   |
| Dennie                       | B9   | 29  |      300.000 | 2026-02-25 | 2026-01 | 00462        | Transfer   | Online | Riska   |
| Hendry Tulus                 | DD2  | 5   |      275.000 | 2026-02-25 | 2026-02 | 00465        | Transfer   | Online | Riska   |
| Andrew                       | C8   | 3   |      250.000 | 2026-02-25 | 2026-01 | 00466        | Transfer   | Online | Riska   |
| Samuel                       | B9   | 80  |      300.000 | 2026-02-25 | 2026-02 | 00468        | VA         | Online | Riska   |
| Edy Hartono                  | JA   | 19  |      300.000 | 2026-02-25 | 2026-02 | 00469        | VA         | Online | Riska   |
| Ricke Surya                  | AX6  | 76  |      250.000 | 2026-02-25 | 2026-02 | 00470        | VA         | Online | Riska   |
| Hani Yesy                    | C6   | 33  |      250.000 | 2026-02-25 | 2026-02 | 00471        | VA         | Online | Riska   |
| Anita Tanu Wijaya            | C1   | 1   |      250.000 | 2026-02-26 | 2026-01 | 00474        | Tunai      | Online | Riska   |
| Ameng                        | C11  | 18  |      250.000 | 2026-02-26 | 2026-02 | 00475        | Transfer   | Online | Riska   |
| Purnama Sari Djaja           | E7   | 29  |      300.000 | 2026-02-26 | 2026-02 | 00476        | Transfer   | Online | Riska   |
| Purnama Sari Djaja           | E7   | 15  |      300.000 | 2026-02-26 | 2026-02 | 00477        | Transfer   | Online | Riska   |
| Nesa Apriani                 | B8   | 67  |      300.000 | 2026-02-26 | 2026-02 | 00479        | VA         | Online | Riska   |
| Nico Cristian                | E7   | 8   |      300.000 | 2026-02-26 | 2026-02 | 00480        | VA         | Online | Riska   |
| Badai Rosamudra              | AX5  | 56  |      200.000 | 2026-02-26 | 2026-02 | 00481        | VA         | Online | Riska   |
| Konnie Gunawan               | B9   | 58  |      300.000 | 2026-02-27 | 2026-02 | 00483        | Transfer   | Online | Riska   |
| Jonathan                     | AX3  | 5   |      200.000 | 2026-02-27 | 2025-09 | 00484        | Transfer   | Online | Riska   |
| Jhoni Candra                 | C1   | 6   |      250.000 | 2026-02-27 | 2026-02 | 00485        | Transfer   | Online | Riska   |
| Ediana Sigalingging          | AM2  | 1   |      300.000 | 2026-02-27 | 2026-02 | 00488        | Tunai      | Loket  | Riska   |
| Ediana Sigalingging          | AM2  | 2   |      300.000 | 2026-02-27 | 2026-02 | 00489        | Tunai      | Loket  | Riska   |
| Rangga Afyan dwiokta         | AX6  | 62  |      250.000 | 2026-02-27 | 2026-02 | 00492        | Transfer   | Online | Riska   |
| Dessy                        | AX7  | 55  |      350.000 | 2026-02-27 | 2026-02 | 00493        | VA         | Online | Riska   |
| Dilla Ardila                 | B10  | 25  |      300.000 | 2026-02-27 | 2026-02 | 00494        | VA         | Online | Riska   |
| Lukmanul Hakim               | C8   | 9   |      250.000 | 2026-02-28 | 2025-11 | 00495        | Transfer   | Online | Riska   |
| Sahrial Lubis                | C2   | 27  |      250.000 | 2026-02-28 | 2026-02 | 00498        | Transfer   | Online | Riska   |
| Denis Sofia Lestari          | AX3  | 55  |      200.000 | 2026-02-28 | 2026-02 | 00501        | Transfer   | Online | Riska   |
| Arie Wibowo                  | AM1  | 7   |      300.000 | 2026-02-28 | 2026-02 | 00504        | Transfer   | Online | Riska   |

## Maret 2026

| Nama               | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC    | Petugas |
| ------------------ | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ------ | ------- |
| Mohamad Yusuf      | B8   | 81  |      300.000 | 2026-03-01 | 2026-02 | 00511        | Transfer   | Online | Riska   |
| Ernest Kurniawan   | DD2  | 8   |      275.000 | 2026-03-02 | 2026-02 | 00516        | Transfer   | Online | Riska   |
| Pramadasyah        | B9   | 32  |      300.000 | 2026-03-02 | 2026-02 | 00522        | Transfer   | Online | Riska   |
| Yudian fantoni     | B9   | 36  |      300.000 | 2026-03-03 | 2026-02 | 00530        | Tunai      | Loket  | Riska   |
| Riecson Wullur     | JA   | 23  |      300.000 | 2026-03-03 | 2026-02 | 00534        | Transfer   | Online | Riska   |
| Riecson Wullur     | DD1  | 20  |      275.000 | 2026-03-03 | 2026-02 | 00535        | Transfer   | Online | Riska   |
| Novriyansyah Karna | AM1  | 11  |      300.000 | 2026-03-03 | 2026-02 | 00537        | Transfer   | Online | Riska   |
| Kristiandi         | B9   | 56  |      300.000 | 2026-03-03 | 2026-02 | 00539        | Transfer   | Online | Riska   |
| Vina Karya         | AL2  | 11  |      300.000 | 2026-03-05 | 2026-02 | 00593        | Tunai      | Loket  | Riska   |
| M E Diah Ayu       | B10  | 27  |      300.000 | 2026-03-05 | 2026-01 | 00596        | Transfer   | Online | Riska   |
| M E Diah Ayu       | B10  | 27  |      300.000 | 2026-03-05 | 2026-02 | 00597        | Transfer   | Online | Riska   |
| Lusia Tungadi      | D8   | 11  |      350.000 | 2026-03-06 | 2026-02 | 00610        | Transfer   | Online | Riska   |
| Riyandi Susanto    | C11  | 28  |      250.000 | 2026-03-06 | 2026-02 | 00612        | Transfer   | Online | Riska   |
| Euis Tuty          | AM2  | 6   |      300.000 | 2026-03-09 | 2026-02 | 00632        | Tunai      | Loket  | Riska   |
| Achamad Yudha      | AX5  | 11  |      250.000 | 2026-03-10 | 2026-02 | 00642        | Transfer   | Online | Arianto |
| Stefanus           | C3   | 9   |      250.000 | 2026-03-11 | 2026-02 | 00656        | Transfer   | Online | Riska   |
| Gusnita            | B9   | 35  |      300.000 | 2026-03-11 | 2026-02 | 00657        | Transfer   | Online | Riska   |
| Nina Nopianti      | B10  | 33  |      300.000 | 2026-03-11 | 2025-11 | 00658        | Transfer   | Online | Riska   |
| Herson             | AX9  | 38  |      300.000 | 2026-03-11 | 2026-02 | 00662        | Transfer   | Online | Riska   |
| Herson             | C6   | 55  |      250.000 | 2026-03-11 | 2026-02 | 00663        | Transfer   | Online | Riska   |
| Herson             | AL2  | 5   |      300.000 | 2026-03-11 | 2026-02 | 00665        | Transfer   | Online | Riska   |
| Putri Alnina       | AX7  | 27  |      350.000 | 2026-03-12 | 2026-02 | 00678        | Transfer   | Online | Riska   |
| Putri Alnina       | JA   | 18  |      300.000 | 2026-03-12 | 2026-02 | 00679        | Transfer   | Online | Riska   |
| Siska              | AX5  | 27  |      250.000 | 2026-03-19 | 2025-06 | 00708        | Transfer   | Online | Riska   |
| Andrew             | C8   | 3   |      250.000 | 2026-03-26 | 2026-02 | 00724        | Transfer   | Online | Riska   |

## April 2026

| Nama               | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC    | Petugas |
| ------------------ | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ------ | ------- |
| Etri Septivia      | B9   | 59  |      300.000 | 2026-04-03 | 2026-01 | 00809        | Transfer   | Online | Riska   |
| Sukardi            | AX5  | 21  |      250.000 | 2026-04-07 | 2026-01 | 00875        | Transfer   | Online | Riska   |
| Dennie             | B9   | 29  |      300.000 | 2026-04-07 | 2026-02 | 00877        | Transfer   | Online | Riska   |
| Ikhwan Rosyiidian  | AX8  | 52  |      300.000 | 2026-04-23 | 2026-01 | 00992        | Tunai      | Loket  | Riska   |
| Tri Septiyanti     | E7   | 7   |      300.000 | 2026-04-24 | 2026-01 | 00999        | Transfer   | Online | Riska   |
| Siska              | AX5  | 27  |      250.000 | 2026-04-25 | 2025-07 | 01002        | Transfer   | Online | Riska   |
| Jonathan           | AX3  | 5   |      200.000 | 2026-04-26 | 2025-11 | 01012        | Transfer   | Online | Riska   |
| Jhoni Candra       | C1   | 6   |      250.000 | 2026-04-27 | 2026-04 | 01013        | Transfer   | Online | Riska   |
| Mohamad Yusuf      | B8   | 81  |      300.000 | 2026-04-27 | 2026-04 | 01014        | Transfer   | Online | Riska   |
| Stephanie Setiawan | DD1  | 6   |      275.000 | 2026-04-27 | 2026-04 | 01015        | Transfer   | Online | Riska   |
| Mugi Arsih         | AX5  | 9   |      250.000 | 2026-04-27 | 2026-04 | 01016        | Transfer   | Online | Riska   |
| Ameng              | C11  | 18  |      250.000 | 2026-04-27 | 2026-04 | 01017        | Transfer   | Online | Riska   |
| Rangga Afyan       | AX6  | 62  |      250.000 | 2026-04-27 | 2026-04 | 01018        | Transfer   | Online | Riska   |
| Lukmanul Hakin     | C8   | 9   |      250.000 | 2026-04-27 | 2026-01 | 01019        | Transfer   | Online | Riska   |
| Dalyano Kayambo    | AX3  | 81  |      200.000 | 2026-04-30 | 2025-07 | 01041        | Transfer   | Online | Riska   |
| Sukardi            | AX5  | 21  |      250.000 | 2026-04-30 | 2026-02 | 01053        | Transfer   | Online | Riska   |

## Mei 2026

| Nama               | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC    | Petugas |
| ------------------ | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ------ | ------- |
| Tjoendro Putjoeng  | E7   | 10  |      300.000 | 2026-05-04 | 2026-02 | 01066        | Transfer   | Online | Riska   |
| Rolis              | AX3  | 51  |      200.000 | 2026-05-07 | 2025-11 | 01133        | Transfer   | Online | Riska   |
| Euis Tuty          | AM2  | 6   |      300.000 | 2026-05-11 | 2026-04 | 01171        | Tunai      | Loket  | Riska   |
| Yudian Fantoni     | B9   | 36  |      300.000 | 2026-05-12 | 2026-04 | 01186        | Tunai      | Loket  | Riska   |
| Gusnita            | B9   | 35  |      300.000 | 2026-05-16 | 2026-04 | 01212        | Transfer   | Online | Sulis   |
| Rohani Susi Herlin | B9   | 10  |      300.000 | 2026-05-16 | 2026-04 | 01213        | Transfer   | Online | Sulis   |
| Rossela            | C6   | 53  |      250.000 | 2026-05-18 | 2026-01 | 01219        | Transfer   | Online | Sulis   |
| Nani Junaeni       | AX8  | 19  |      300.000 | 2026-05-18 | 2026-04 | 01220        | Transfer   | Online | Sulis   |
| Siat Suk Jin       | C5   | 5   |      250.000 | 2026-05-21 | 2026-03 | 01241        | Tunai      | Loket  | Sulis   |
| Dennie             | B9   | 29  |      300.000 | 2026-05-25 | 2026-04 | 01249        | Transfer   | Online | Sulis   |
| Meti Oktavia       | DD2  | 2   |      275.000 | 2026-05-25 | 2026-06 | 01250        | Transfer   | Online | Sulis   |
| Ye Lie             | AL2  | 9   |      300.000 | 2026-05-26 | 2026-03 | 01266        | Transfer   | Online | Sulis   |
| Susanti            | E7   | 32  |      300.000 | 2026-05-26 | 2026-03 | 01271        | Transfer   | Online | Sulis   |
| Susanti            | E7   | 33  |      300.000 | 2026-05-26 | 2026-03 | 01272        | Transfer   | Online | Sulis   |
| Lukmanul Hakim     | C8   | 9   |      250.000 | 2026-05-27 | 2026-02 | 01277        | Transfer   | Online | Sulis   |
| Tjoendro Putjoeng  | E7   | 10  |      300.000 | 2026-05-27 | 2026-04 | 01278        | Transfer   | Online | Sulis   |
| M E Diah Ayu       | B10  | 27  |      300.000 | 2026-05-28 | 2026-04 | 01282        | Transfer   | Online | Sulis   |
| Ikhwan Rosyiidian  | AX8  | 52  |      300.000 | 2026-05-29 | 2026-02 | 01289        | Transfer   | Online | Sulis   |
| Andrew             | C8   | 3   |      250.000 | 2026-05-30 | 2026-04 | 01301        | Transfer   | Online | Sulis   |
| Sukardi            | AX5  | 21  |      250.000 | 2026-05-30 | 2026-03 | 01308        | Transfer   | Online | Sulis   |

## Juni 2026

| Nama               | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC    | Petugas |
| ------------------ | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ------ | ------- |
| Lusiana            | AX5  | 7   |      250.000 | 2026-06-03 | 2026-04 | 01335        | Transfer   | Online | Sulis   |
| Setiahandy Bastian | AX5  | 71  |      250.000 | 2026-06-05 | 2026-04 | 01387        | Transfer   | Online | Sulis   |
| Setly Jecsen       | AX5  | 66  |      200.000 | 2026-06-07 | 2025-04 | 01417        | Transfer   | Online | Sulis   |
| Etri Septivia      | B9   | 59  |      300.000 | 2026-06-17 | 2026-04 | 01524        | Transfer   | Online | Sulis   |
| Mohammad Ammar     | B9   | 18  |      300.000 | 2026-06-24 | 2026-01 | 01540        | Tunai      | Loket  | Sulis   |
| Mohammad Ammar     | B9   | 18  |      300.000 | 2026-06-24 | 2026-02 | 01543        | Transfer   | Online | Sulis   |
| Jonathan           | AX3  | 5   |      200.000 | 2026-06-26 | 2026-01 | 01564        | Transfer   | Online | Sulis   |
| Natasya Veronica   | B10  | 30  |      300.000 | 2026-06-28 | 2026-01 | 01575        | Transfer   | Online | Sulis   |
| Sukardi            | AX5  | 21  |      250.000 | 2026-06-30 | 2026-04 | 01602        | Transfer   | Online | Sulis   |

## Juli 2026

| Nama         | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC    | Petugas |
| ------------ | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ------ | ------- |
| Putri Alvina | JA   | 18  |      300.000 | 2026-07-01 | 2026-04 | 01612        | Transfer   | Online | Sulis   |
| Siska        | AX5  | 27  |      250.000 | 2026-07-07 | 2025-09 | 01701        | Transfer   | Online | Sulis   |
| Rossela      | C6   | 53  |      250.000 | 2026-07-10 | 2026-02 | 01744        | Transfer   | Online | Sulis   |
| Jonathan     | AX3  | 5   |      200.000 | 2026-07-25 | 2026-02 | 01839        | Transfer   | Online | Sulis   |
| Siat Suk Jin | C5   | 5   |      250.000 | 2026-07-27 | 2026-04 | 01848        | Tunai      | Loket  | Sulis   |

## Agustus 2026

| Nama                     | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC    | Petugas |
| ------------------------ | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ------ | ------- |
| Ikhwan Rosyiidian        | AX8  | 52  |      300.000 | 2026-08-03 | 2026-04 | 01919        | Tunai      | Loket  | Sulis   |
| Lukmanul Hakim           | C8   | 9   |      250.000 | 2026-08-05 | 2026-04 | 01939        | Transfer   | Online | Sulis   |
| Jhon Delon               | AX5  | 78  |      200.000 | 2026-08-06 | 2026-08 | 01966        | Tunai      | Loket  | Sulis   |
| Charlie Chandra          | AX5  | 38  |      200.000 | 2026-08-06 | 2026-08 | 01967        | Transfer   | Online | Sulis   |
| Nova Diana               | B10  | 6   |      300.000 | 2026-08-06 | 2026-08 | 01969        | Transfer   | Online | Sulis   |
| Vilani Osa               | AX5  | 65  |      250.000 | 2026-08-06 | 2026-08 | 01970        | VA         | Online | Sulis   |
| Yani Lesatri             | AX7  | 6   |      350.000 | 2026-08-06 | 2026-08 | 01971        | VA         | Online | Sulis   |
| Dewi Santosari           | C5   | 9   |      250.000 | 2026-08-06 | 2026-08 | 01972        | VA         | Online | Sulis   |
| Regina Adytia            | AX8  | 3   |      300.000 | 2026-08-06 | 2026-08 | 01973        | VA         | Online | Sulis   |
| Anista Retno             | JA   | 16  |      300.000 | 2026-08-06 | 2026-08 | 01974        | VA         | Online | Sulis   |
| Devi Febrian             | DD1  | 18  |      275.000 | 2026-08-06 | 2026-08 | 01975        | VA         | Online | Sulis   |
| Imas Rahayu Ningsih      | AX8  | 25  |      300.000 | 2026-08-06 | 2026-08 | 01976        | VA         | Online | Sulis   |
| Kartika Melanda          | AX6  | 27  |      350.000 | 2026-08-06 | 2026-08 | 01978        | VA         | Online | Sulis   |
| Deri Yatno               | AX7  | 29  |      350.000 | 2026-08-06 | 2026-08 | 01979        | VA         | Online | Sulis   |
| Muhammad Syukron         | C6   | 19  |      250.000 | 2026-08-06 | 2026-08 | 01980        | VA         | Online | Sulis   |
| Albertus Chandra         | AX8  | 60  |      300.000 | 2026-08-06 | 2026-08 | 01981        | VA         | Online | Sulis   |
| Joni                     | AX7  | 23  |      350.000 | 2026-08-06 | 2026-08 | 01982        | VA         | Online | Sulis   |
| Mok Nyian Djin           | AX7  | 35  |      350.000 | 2026-08-07 | 2026-08 | 01986        | Transfer   | Online | Sulis   |
| Andri Mustakim           | AX5  | 5   |      250.000 | 2026-08-07 | 2026-07 | 01987        | Transfer   | Online | Sulis   |
| Robert                   | AX8  | 6   |      300.000 | 2026-08-07 | 2026-08 | 01988        | Transfer   | Online | Sulis   |
| Henni Johanes            | AX5  | 59  |      250.000 | 2026-08-07 | 2026-08 | 01989        | Transfer   | Online | Sulis   |
| Antonius Yulian          | AX3  | 83  |      200.000 | 2026-08-07 | 2026-08 | 01990        | VA         | Online | Sulis   |
| Muhammad Fachdilah       | AX6  | 70  |      250.000 | 2026-08-07 | 2026-08 | 01991        | VA         | Online | Sulis   |
| Hanafi                   | C7   | 26  |      250.000 | 2026-08-07 | 2026-08 | 01992        | VA         | Online | Sulis   |
| Setia Karma Mulia Suteja | B7   | 30  |      300.000 | 2026-08-07 | 2026-08 | 01993        | VA         | Online | Sulis   |
| Anita Carolina           | B10  | 32  |      300.000 | 2026-08-07 | 2026-08 | 01994        | VA         | Online | Sulis   |
| Drya Nursanti            | AX5  | 72  |      200.000 | 2026-08-07 | 2026-08 | 01995        | VA         | Online | Sulis   |
| Friciel Yael             | B8   | 79  |      300.000 | 2026-08-07 | 2026-08 | 01996        | VA         | Online | Sulis   |
| Rully Angga              | AX7  | 12  |      350.000 | 2026-08-07 | 2026-08 | 01997        | VA         | Online | Sulis   |
| Fida Hussain             | B10  | 33  |      300.000 | 2026-08-08 | 2026-06 | 01998        | Transfer   | Online | Sulis   |
| Nani Junaeni             | AX8  | 19  |      300.000 | 2026-08-08 | 2026-07 | 02000        | Transfer   | Online | Sulis   |
| Sapto Rini Rushapsari    | AX6  | 7   |      350.000 | 2026-08-08 | 2026-08 | 02001        | Transfer   | Online | Sulis   |
| Fida Hussain             | B10  | 33  |      300.000 | 2026-08-08 | 2026-07 | 02002        | Transfer   | Online | Sulis   |
| Rita                     | B10  | 21  |      300.000 | 2026-08-08 | 2026-08 | 02004        | VA         | Online | Sulis   |
| Else                     | DD1  | 2   |      275.000 | 2026-08-08 | 2026-08 | 02006        | VA         | Online | Sulis   |
| Dila Ardila              | B10  | 25  |      300.000 | 2026-08-09 | 2026-08 | 02007        | VA         | Online | Sulis   |
| Edy Gunawan              | AX3  | 9   |      200.000 | 2026-08-09 | 2026-08 | 02008        | VA         | Online | Sulis   |
| Siti Nurwati             | AX6  | 17  |      350.000 | 2026-08-09 | 2026-08 | 02009        | VA         | Online | Sulis   |
| Riyandi Susanto          | C11  | 28  |      250.000 | 2026-08-10 | 2026-07 | 02018        | Transfer   | Online | Sulis   |
| Euis Tuty                | AM2  | 6   |      300.000 | 2026-08-11 | 2026-07 | 02029        | Tunai      | Loket  | Sulis   |
| Herson                   | AX9  | 38  |      300.000 | 2026-08-11 | 2026-07 | 02036        | Transfer   | Online | Sulis   |
| Herson                   | AL2  | 5   |      300.000 | 2026-08-11 | 2026-07 | 02037        | Transfer   | Online | Sulis   |
| Herson                   | D8   | 5   |      350.000 | 2026-08-11 | 2026-07 | 02039        | Transfer   | Online | Sulis   |
| Herson                   | C6   | 55  |      250.000 | 2026-08-11 | 2026-07 | 02042        | Transfer   | Online | Sulis   |
| Lusia Tungadi            | D8   | 11  |      350.000 | 2026-08-12 | 2026-07 | 02056        | Transfer   | Online | Sulis   |
| Rochmat Hajati           | C10  | 5   |      250.000 | 2026-08-12 | 2026-07 | 02057        | Transfer   | Online | Sulis   |
| Pramadasyah              | B9   | 32  |      300.000 | 2026-08-12 | 2026-07 | 02059        | Transfer   | Online | Sulis   |
| Linda                    | B9   | 79  |      300.000 | 2026-08-12 | 2026-07 | 02060        | Transfer   | Online | Sulis   |
| Djony                    | AX7  | 2   |      350.000 | 2026-08-21 | 2026-01 | 02093        | Transfer   | Online | Sulis   |
| Mohammad Ammar Kurnia    | B9   | 18  |      300.000 | 2026-08-24 | 2026-04 | 02105        | Tunai      | Loket  | Sulis   |
| Meti Oktavia             | DD2  | 2   |      275.000 | 2026-08-25 | 2026-09 | 02115        | Transfer   | Online | Sulis   |
| Liyanawati               | AX7  | 16  |      350.000 | 2026-08-26 | 2026-09 | 02120        | Transfer   | Online | Sulis   |
| Visilia Linata           | AX3  | 65  |      200.000 | 2026-08-27 | 2026-09 | 02124        | Tunai      | Loket  | Sulis   |
| Wulan Januri             | C6   | 37  |      250.000 | 2026-08-27 | 2026-09 | 02128        | Transfer   | Online | Sulis   |
| Handrian Wijaya          | DD1  | 5   |      275.000 | 2026-08-27 | 2026-09 | 02129        | Transfer   | Online | Sulis   |
| Stefanus Andre           | AX9  | 50  |      300.000 | 2026-08-28 | 2026-09 | 02134        | Transfer   | Online | Sulis   |
| Jhon Delon               | AX5  | 78  |      200.000 | 2026-08-31 | 2026-09 | 02146        | Tunai      | Loket  | Sulis   |
| Melisa Apriyani          | AX5  | 80  |      200.000 | 2026-08-31 | 2026-09 | 02154        | Transfer   | Online | Sulis   |

## September 2026

| Nama                     | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC    | Petugas |
| ------------------------ | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ------ | ------- |
| Yusron Fauzi             | C8   | 12  |      250.000 | 2026-09-01 | 2026-09 | 02160        | Tunai      | Loket  | Sulis   |
| Ordie                    | B8   | 10  |      300.000 | 2026-09-01 | 2026-09 | 02161        | Transfer   | Online | Sulis   |
| Viyan Andhika            | AX7  | 19  |      350.000 | 2026-09-01 | 2026-09 | 02162        | Transfer   | Online | Sulis   |
| Septiany Ganescya        | C7   | 66  |      250.000 | 2026-09-01 | 2026-09 | 02163        | Transfer   | Online | Sulis   |
| Rezky Iriansyah          | B9   | 78  |      300.000 | 2026-09-01 | 2026-09 | 02165        | Transfer   | Online | Sulis   |
| Amelia Silviana          | C7   | 32  |      250.000 | 2026-09-01 | 2026-09 | 02168        | Transfer   | Online | Sulis   |
| Efendi                   | B8   | 75  |      300.000 | 2026-09-01 | 2025-04 | 02169        | Transfer   | Online | Sulis   |
| Luman                    | AX7  | 37  |      350.000 | 2026-09-01 | 2026-09 | 02170        | Transfer   | Online | Sulis   |
| Tanu Wijaya              | DD2  | 7   |      275.000 | 2026-09-02 | 2026-09 | 02175        | Transfer   | Online | Sulis   |
| Febriyanti Cahaya        | AX5  | 23  |      250.000 | 2026-09-02 | 2026-09 | 02176        | Transfer   | Online | Sulis   |
| Selvy                    | AX8  | 7   |      300.000 | 2026-09-02 | 2026-09 | 02177        | Transfer   | Online | Sulis   |
| Ani / Evi GBI            | JA   | 1   |      300.000 | 2026-09-02 | 2026-09 | 02179        | Transfer   | Online | Sulis   |
| Purnawati SE             | AX6  | 9   |      350.000 | 2026-09-02 | 2026-09 | 02183        | VA         | Online | Sulis   |
| Muhammad Syukron         | C6   | 19  |      250.000 | 2026-09-02 | 2026-09 | 02184        | VA         | Online | Sulis   |
| Silviana Victoria        | DD1  | 16  |      275.000 | 2026-09-02 | 2026-09 | 02185        | VA         | Online | Sulis   |
| Giffari Lestionanda      | B8   | 27  |      300.000 | 2026-09-02 | 2026-09 | 02186        | VA         | Online | Sulis   |
| Bambang Supriyadi        | C7   | 38  |      250.000 | 2026-09-02 | 2026-09 | 02187        | VA         | Online | Sulis   |
| Eriyono Budi             | AX3  | 69  |      200.000 | 2026-09-02 | 2026-09 | 02188        | VA         | Online | Sulis   |
| Herman                   | DD3  | 10  |      275.000 | 2026-09-02 | 2026-09 | 02189        | VA         | Online | Sulis   |
| Leo Baratanata           | DD1  | 15  |      275.000 | 2026-09-02 | 2026-09 | 02190        | VA         | Online | Sulis   |
| Muhammad Fachdilah       | AX6  | 70  |      250.000 | 2026-09-02 | 2026-09 | 02191        | VA         | Online | Sulis   |
| Mohamad Arif             | E7   | 18  |      300.000 | 2026-09-02 | 2026-09 | 02193        | VA         | Online | Sulis   |
| Charlie Chandra          | AX5  | 38  |      200.000 | 2026-09-02 | 2026-09 | 02194        | VA         | Online | Sulis   |
| Felix Hadianto           | AX5  | 76  |      200.000 | 2026-09-02 | 2026-09 | 02195        | VA         | Online | Sulis   |
| Evi Yulianti             | AX3  | 35  |      200.000 | 2026-09-02 | 2026-09 | 02196        | VA         | Online | Sulis   |
| Kartika Melanda          | AX6  | 27  |      350.000 | 2026-09-02 | 2026-09 | 02197        | VA         | Online | Sulis   |
| Yuliana                  | C6   | 31  |      250.000 | 2026-09-03 | 2026-09 | 02199        | Transfer   | Online | Sulis   |
| Andini Laurensia         | C2   | 22  |      250.000 | 2026-09-03 | 2026-09 | 02200        | Transfer   | Online | Sulis   |
| Siti Suprapti            | AX2  | 8&9 |      500.000 | 2026-09-03 | 2026-09 | 02203        | Transfer   | Online | Sulis   |
| Dendy                    | AX3  | 91  |      200.000 | 2026-09-03 | 2026-09 | 02207        | Transfer   | Online | Sulis   |
| Melina                   | AX8  | 36  |      300.000 | 2026-09-03 | 2026-09 | 02208        | VA         | Online | Sulis   |
| Edy Gunawan              | AX3  | 9   |      200.000 | 2026-09-03 | 2026-09 | 02209        | VA         | Online | Sulis   |
| Anita Carolina           | B10  | 32  |      300.000 | 2026-09-03 | 2026-09 | 02210        | VA         | Online | Sulis   |
| Hanafi                   | C7   | 26  |      250.000 | 2026-09-03 | 2026-09 | 02211        | VA         | Online | Sulis   |
| Verawati Wijaya          | AX5  | 18  |      200.000 | 2026-09-03 | 2026-09 | 02212        | VA         | Online | Sulis   |
| Go Wie Gwan              | AX8  | 12  |      300.000 | 2026-09-03 | 2026-09 | 02213        | VA         | Online | Sulis   |
| Noviani Harianto         | C11  | 10  |      250.000 | 2026-09-03 | 2026-09 | 02214        | VA         | Online | Sulis   |
| Vilani Osa               | AX5  | 65  |      250.000 | 2026-09-03 | 2026-09 | 02215        | VA         | Online | Sulis   |
| Yani Lestari             | AX7  | 6   |      350.000 | 2026-09-03 | 2026-09 | 02216        | VA         | Online | Sulis   |
| Else                     | DD1  | 2   |      275.000 | 2026-09-03 | 2026-09 | 02218        | VA         | Online | Sulis   |
| Fillia Claudja           | D7   | 3   |      350.000 | 2026-09-04 | 2026-09 | 02219        | Transfer   | Online | Sulis   |
| Chandra                  | AX6  | 19  |      350.000 | 2026-09-04 | 2026-09 | 02223        | VA         | Online | Sulis   |
| Deri Yatno               | AX7  | 29  |      350.000 | 2026-09-04 | 2026-09 | 02224        | VA         | Online | Sulis   |
| Ajeng                    | DD1  | 17  |      275.000 | 2026-09-04 | 2026-09 | 02225        | VA         | Online | Sulis   |
| Dila Ardila              | B10  | 25  |      300.000 | 2026-09-04 | 2026-09 | 02226        | VA         | Online | Sulis   |
| Noviana                  | AX7  | 33  |      350.000 | 2026-09-04 | 2026-09 | 02227        | VA         | Online | Sulis   |
| Lista Akdriaty           | C3   | 7   |      250.000 | 2026-09-04 | 2026-09 | 02228        | VA         | Online | Sulis   |
| Drya Nursanti            | AX5  | 72  |      200.000 | 2026-09-04 | 2026-09 | 02229        | VA         | Online | Sulis   |
| Anista Retno             | JA   | 16  |      300.000 | 2026-09-04 | 2026-09 | 02230        | VA         | Online | Sulis   |
| Endah Siwi               | C5   | 10  |      250.000 | 2026-09-04 | 2026-09 | 02231        | VA         | Online | Sulis   |
| Linda Wati               | B10  | 39  |      300.000 | 2026-09-04 | 2026-09 | 02232        | VA         | Online | Sulis   |
| Joni                     | AX7  | 23  |      350.000 | 2026-09-04 | 2026-09 | 02233        | VA         | Online | Sulis   |
| Laura Lorenza            | AX3  | 67  |      200.000 | 2026-09-04 | 2026-09 | 02234        | VA         | Online | Sulis   |
| Sonya Elia Chandra       | AX5  | 20  |      200.000 | 2026-09-05 | 2026-09 | 02236        | Transfer   | Online | Sulis   |
| Rike Saxena              | C9   | 5   |      250.000 | 2026-09-05 | 2026-09 | 02237        | VA         | Online | Sulis   |
| Kartika Puspa            | AX7  | 8   |      350.000 | 2026-09-05 | 2026-09 | 02238        | VA         | Online | Sulis   |
| Albertus Chandra         | AX8  | 60  |      300.000 | 2026-09-05 | 2026-09 | 02239        | VA         | Online | Sulis   |
| Linda Apriani            | AX5  | 70  |      200.000 | 2026-09-05 | 2026-09 | 02242        | VA         | Online | Sulis   |
| Nani Junaeni             | AX8  | 19  |      300.000 | 2026-09-06 | 2026-09 | 02243        | Transfer   | Online | Sulis   |
| Hans Jakal               | C1   | 12  |      250.000 | 2026-09-07 | 2026-09 | 02245        | Tunai      | Loket  | Sulis   |
| Nova Diana               | B10  | 6   |      300.000 | 2026-09-07 | 2026-09 | 02248        | Transfer   | Online | Sulis   |
| Wirya Dharmawan          | AX3  | 71  |      200.000 | 2026-09-07 | 2026-09 | 02249        | Transfer   | Online | Sulis   |
| Devi Febrian             | DD1  | 18  |      275.000 | 2026-09-07 | 2026-09 | 02253        | VA         | Online | Sulis   |
| Rinaldy Tarmadi          | AX5  | 39  |      250.000 | 2026-09-07 | 2026-09 | 02254        | VA         | Online | Sulis   |
| Friciel Yael             | B8   | 79  |      300.000 | 2026-09-07 | 2026-09 | 02256        | VA         | Online | Sulis   |
| Noer Hartati             | C6   | 59  |      250.000 | 2026-09-07 | 2026-09 | 02257        | VA         | Online | Sulis   |
| Sapto Rini Rushapsari    | AX6  | 7   |      350.000 | 2026-09-08 | 2026-09 | 02258        | Transfer   | Online | Sulis   |
| Dalyano Kayambo          | AX3  | 81  |      200.000 | 2026-09-08 | 2025-09 | 02260        | Transfer   | Online | Sulis   |
| Berli                    | AX8  | 28  |      300.000 | 2026-09-08 | 2026-09 | 02265        | VA         | Online | Sulis   |
| Danang Riyadi            | AX8  | 51  |      300.000 | 2026-09-08 | 2026-09 | 02266        | VA         | Online | Sulis   |
| Rizki Dermawan           | AX6  | 23  |      350.000 | 2026-09-08 | 2026-09 | 02267        | VA         | Online | Sulis   |
| Ervina                   | AM1  | 5   |      300.000 | 2026-09-08 | 2026-09 | 02268        | VA         | Online | Sulis   |
| Friska                   | AX5  | 57  |      250.000 | 2026-09-09 | 2026-09 | 02269        | Tunai      | Loket  | Sulis   |
| Sinta Dewi               | AX5  | 33  |      250.000 | 2026-09-09 | 2026-09 | 02271        | Transfer   | Online | Sulis   |
| Yuki Erik                | B10  | 8   |      300.000 | 2026-09-09 | 2026-09 | 02272        | VA         | Online | Sulis   |
| Agnes Wiwik              | B10  | 77  |      300.000 | 2026-09-09 | 2026-09 | 02273        | VA         | Online | Sulis   |
| Ervina                   | AM1  | 3   |      300.000 | 2026-09-09 | 2026-09 | 02274        | VA         | Online | Sulis   |
| Sugandi                  | D8   | 2   |      350.000 | 2026-09-09 | 2026-09 | 02275        | VA         | Online | Sulis   |
| Lisa Loandha             | C11  | 20  |      250.000 | 2026-09-10 | 2026-09 | 02278        | Transfer   | Online | Sulis   |
| Mok Nyian Djin           | AX7  | 35  |      350.000 | 2026-09-10 | 2026-09 | 02279        | Transfer   | Online | Sulis   |
| Juan Ignatius            | AX3  | 1   |      200.000 | 2026-09-10 | 2026-09 | 02280        | Transfer   | Online | Sulis   |
| Abdul Azis Saifuddin     | C11  | 8   |      250.000 | 2026-09-10 | 2026-09 | 02282        | Transfer   | Online | Sulis   |
| IDM 2 / Indomarco        | E7   | 27  |      300.000 | 2026-09-10 | 2026-09 | 02283        | Transfer   | Online | Sulis   |
| IDM 2 / Indomarco        | E7   | 28  |      300.000 | 2026-09-10 | 2026-09 | 02284        | Transfer   | Online | Sulis   |
| Johanna Hermawan         | DD2  | 3   |      275.000 | 2026-09-10 | 2026-09 | 02285        | Transfer   | Online | Sulis   |
| Kuswanto                 | AM2  | 19  |      300.000 | 2026-09-10 | 2026-09 | 02286        | VA         | Online | Sulis   |
| Kuswanto                 | AM2  | 17  |      300.000 | 2026-09-10 | 2026-09 | 02287        | VA         | Online | Sulis   |
| Fransisca Natalia        | C11  | 16  |      250.000 | 2026-09-10 | 2026-09 | 02288        | VA         | Online | Sulis   |
| Imas Rahayu Ningsih      | AX8  | 25  |      300.000 | 2026-09-10 | 2026-09 | 02289        | VA         | Online | Sulis   |
| Anastasia Dame           | AX7  | 7   |      350.000 | 2026-09-10 | 2026-09 | 02290        | VA         | Online | Sulis   |
| Herson                   | D8   | 3   |      350.000 | 2026-09-11 | 2026-09 | 02295        | Transfer   | Online | Sulis   |
| Herson                   | D8   | 7   |      350.000 | 2026-09-11 | 2026-09 | 02297        | Transfer   | Online | Sulis   |
| Herson                   | C7   | 28  |      250.000 | 2026-09-11 | 2026-09 | 02298        | Transfer   | Online | Sulis   |
| Robert                   | AX8  | 6   |      300.000 | 2026-09-11 | 2026-09 | 02301        | Transfer   | Online | Sulis   |
| Seftiningtyas            | AX3  | 17  |      200.000 | 2026-09-11 | 2026-09 | 02302        | VA         | Online | Sulis   |
| Siti Nurwati             | AX6  | 17  |      350.000 | 2026-09-11 | 2026-09 | 02303        | VA         | Online | Sulis   |
| Setia Karma Mulia Suteja | B7   | 30  |      300.000 | 2026-09-11 | 2026-09 | 02304        | VA         | Online | Sulis   |
| Tham Alifin              | AX5  | 29  |      250.000 | 2026-09-14 | 2026-09 | 02306        | Transfer   | Online | Sulis   |
| Tham Alifin              | AX6  | 6   |      250.000 | 2026-09-14 | 2026-09 | 02307        | Transfer   | Online | Sulis   |
| Ernest Kurniawan         | DD2  | 8   |      275.000 | 2026-09-14 | 2026-09 | 02309        | Transfer   | Online | Sulis   |
| Elly Swanti              | C1   | 11  |      250.000 | 2026-09-14 | 2026-09 | 02314        | VA         | Online | Sulis   |
| Rita                     | B10  | 21  |      300.000 | 2026-09-14 | 2026-09 | 02316        | VA         | Online | Sulis   |
| Jaya Iskandar            | C9   | 8   |      250.000 | 2026-09-15 | 2026-09 | 02317        | Tunai      | Loket  | Sulis   |
| Arie Wibowo              | AM1  | 7   |      300.000 | 2026-09-15 | 2026-09 | 02320        | Transfer   | Online | Sulis   |
| Lina                     | AX5  | 68  |      200.000 | 2026-09-15 | 2026-09 | 02321        | VA         | Online | Sulis   |
| Teguh Santoso            | C3   | 3   |      250.000 | 2026-09-15 | 2026-09 | 02322        | VA         | Online | Sulis   |
| Kristianto               | JA   | 2   |      300.000 | 2026-09-15 | 2026-09 | 02323        | VA         | Online | Sulis   |
| Rully Angga              | AX7  | 12  |      350.000 | 2026-09-15 | 2026-09 | 02324        | VA         | Online | Sulis   |
| Sabeth                   | AX8  | 30  |      300.000 | 2026-09-15 | 2026-09 | 02325        | VA         | Online | Sulis   |
| Henni Johanes            | AX5  | 59  |      250.000 | 2026-09-16 | 2026-09 | 02328        | Transfer   | Online | Sulis   |
| Darwin                   | AX6  | 25  |      350.000 | 2026-09-16 | 2026-09 | 02333        | Transfer   | Online | Sulis   |
| Rika                     | AM2  | 10  |      300.000 | 2026-09-17 | 2026-09 | 02338        | VA         | Online | Sulis   |
| Sugeng Rochili           | DD3  | 2   |      275.000 | 2026-09-17 | 2026-09 | 02339        | VA         | Online | Sulis   |
| Nico Cristian            | E7   | 8   |      300.000 | 2026-09-17 | 2026-09 | 02340        | VA         | Online | Sulis   |
| Sari Dewi                | D7   | 7   |      350.000 | 2026-09-17 | 2026-09 | 02341        | VA         | Online | Sulis   |
| Hendi                    | DD1  | 7   |      275.000 | 2026-09-17 | 2026-09 | 02342        | VA         | Online | Sulis   |
| Regina Adytia            | AX8  | 3   |      300.000 | 2026-09-17 | 2026-09 | 02343        | VA         | Online | Sulis   |
| Jangkau Lintas Nusantara | E7   | 50  |      300.000 | 2026-09-18 | 2026-09 | 02346        | Transfer   | Online | Sulis   |
| Yulin                    | AX8  | 39  |      300.000 | 2026-09-18 | 2026-09 | 02347        | VA         | Online | Sulis   |
| GSJA Abbacare            | AL2  | 12  |      300.000 | 2026-09-19 | 2026-09 | 02350        | Transfer   | Online | Sulis   |
| Fransisca Herlin         | B8   | 35  |      300.000 | 2026-09-19 | 2026-09 | 02351        | VA         | Online | Sulis   |
| Hendry                   | DD1  | 9   |      275.000 | 2026-09-19 | 2026-09 | 02352        | VA         | Online | Sulis   |
| Shafa Salsabila          | AM2  | 18  |      300.000 | 2026-09-20 | 2026-09 | 02353        | VA         | Online | Sulis   |
| Ruli Pram                | E7   | 26  |      300.000 | 2026-09-21 | 2026-09 | 02354        | Tunai      | Loket  | Sulis   |
| Vivin Rosalia            | C10  | 2   |      250.000 | 2026-09-21 | 2026-09 | 02357        | VA         | Online | Sulis   |
| Indrawan                 | AX3  | 39  |      200.000 | 2026-09-21 | 2026-09 | 02358        | VA         | Online | Sulis   |
| Ali Ardianto             | DD1  | 8   |      275.000 | 2026-09-22 | 2026-04 | 02359        | Transfer   | Online | Sulis   |
| Yenni                    | C5   | 12  |      250.000 | 2026-09-22 | 2026-09 | 02363        | VA         | Online | Sulis   |
| Rangga Afyan Dwiokta     | AX6  | 62  |      250.000 | 2026-09-23 | 2026-09 | 02364        | Transfer   | Online | Sulis   |
| Dewi Santosari           | C5   | 9   |      250.000 | 2026-09-23 | 2026-09 | 02365        | VA         | Online | Sulis   |
| Gwat Lie                 | AM1  | 1   |      300.000 | 2026-09-23 | 2026-09 | 02366        | VA         | Online | Sulis   |
| Fransisca                | AX7  | 17  |      350.000 | 2026-09-23 | 2026-09 | 02367        | VA         | Online | Sulis   |
| Meliana                  | C7   | 58  |      250.000 | 2026-09-24 | 2026-09 | 02369        | Transfer   | Online | Sulis   |
| Henry Tulus              | DD2  | 5   |      275.000 | 2026-09-25 | 2026-09 | 02374        | Transfer   | Online | Sulis   |
| Jhoni Candra             | C1   | 6   |      250.000 | 2026-09-25 | 2026-09 | 02375        | Transfer   | Online | Sulis   |
| Hadianto                 | C8   | 11  |      250.000 | 2026-09-25 | 2026-09 | 02378        | VA         | Online | Sulis   |
| Helika                   | AX5  | 35  |      250.000 | 2026-09-25 | 2026-09 | 02380        | VA         | Online | Sulis   |
| Ricke Surya              | AX6  | 76  |      250.000 | 2026-09-25 | 2026-09 | 02381        | VA         | Online | Sulis   |
| Susanti                  | B8   | 76  |      300.000 | 2026-09-26 | 2026-09 | 02383        | Transfer   | Online | Sulis   |
| Julio Andrian            | AX3  | 29  |      200.000 | 2026-09-28 | 2026-09 | 02384        | Tunai      | Loket  | Sulis   |
| Stephanie Setiawan       | DD1  | 6   |      275.000 | 2026-09-28 | 2026-09 | 02392        | Transfer   | Online | Sulis   |
| Ameng                    | C11  | 18  |      250.000 | 2026-09-28 | 2026-09 | 02393        | Transfer   | Online | Sulis   |
| Rosia Daeli              | C6   | 27  |      250.000 | 2026-09-28 | 2026-09 | 02395        | VA         | Online | Sulis   |
| Karim Amirullah          | B10  | 16  |      300.000 | 2026-09-28 | 2026-09 | 02396        | VA         | Online | Sulis   |
| Sahrial Lubis            | C2   | 27  |      250.000 | 2026-09-29 | 2026-09 | 02400        | Transfer   | Online | Sulis   |
| Edy Hartono              | JA   | 19  |      300.000 | 2026-09-29 | 2026-09 | 02402        | Transfer   | Online | Sulis   |
| Antonius Yulian          | AX3  | 83  |      200.000 | 2026-09-29 | 2026-09 | 02403        | VA         | Online | Sulis   |
| Denis Sofia Lestari      | AX3  | 55  |      200.000 | 2026-09-29 | 2026-09 | 02404        | VA         | Online | Sulis   |
| Setiadi Sutedjo          | AX6  | 58  |      250.000 | 2026-09-29 | 2026-09 | 02405        | VA         | Online | Sulis   |
| Badai Rosamudra          | AX5  | 56  |      200.000 | 2026-09-29 | 2026-09 | 02406        | VA         | Online | Sulis   |
| Cedy Chandra             | AX6  | 3   |      350.000 | 2026-09-29 | 2026-09 | 02407        | VA         | Online | Sulis   |
| Dessy                    | AX7  | 55  |      350.000 | 2026-09-29 | 2026-09 | 02408        | VA         | Online | Sulis   |
| Ediana Sigalingging      | AM2  | 1   |      300.000 | 2026-09-30 | 2026-09 | 02410        | Tunai      | Loket  | Sulis   |
| Ediana Sigalingging      | AM2  | 2   |      300.000 | 2026-09-30 | 2026-09 | 02411        | Tunai      | Loket  | Sulis   |
| Juliana Putri            | E7   | 2   |      300.000 | 2026-09-30 | 2026-09 | 02412        | Tunai      | Loket  | Sulis   |
| Jefri Jsianturi          | B10  | 28  |      300.000 | 2026-09-30 | 2026-09 | 02414        | Transfer   | Online | Sulis   |
| Mugi Arsih               | AX5  | 9   |      250.000 | 2026-09-30 | 2026-09 | 02416        | Transfer   | Online | Sulis   |
| Yala Sanjaya             | JA   | 23  |      300.000 | 2026-09-30 | 2026-09 | 02418        | Transfer   | Online | Sulis   |
| Yala Sanjaya             | DD1  | 20  |      275.000 | 2026-09-30 | 2026-09 | 02419        | Transfer   | Online | Sulis   |
| Konnie Gunawan           | B9   | 58  |      300.000 | 2026-09-30 | 2026-09 | 02420        | Transfer   | Online | Sulis   |
| Uhyana Jaya              | E7   | 19  |      300.000 | 2026-09-30 | 2026-09 | 02423        | VA         | Online | Sulis   |

## Oktober 2026

| Nama               | Blok | No. | Nominal (Rp) | Tgl Bayar  | Periode | No. Kwitansi | Cara Bayar | PIC    | Petugas | Catatan            |
| ------------------ | ---- | --- | -----------: | ---------- | ------- | ------------ | ---------- | ------ | ------- | ------------------ |
| Mohammad Yusuf     | B8   | 81  |      300.000 | 2026-10-01 | 2026-09 | 02428        | Transfer   | Online | Sulis   |                    |
| Iwan Kiswanto      | B7   | 39  |      300.000 | 2026-10-01 | 2026-09 | 02433        | Transfer   | Online | Sulis   |                    |
| Siska              | AX5  | 27  |      250.000 | 2026-10-01 | 2025-11 | 02436        | Transfer   | Online | Sulis   |                    |
| Sujito             | D7   | 12  |      350.000 | 2026-10-01 | 2026-09 | 02442        | Transfer   | Online | Sulis   |                    |
| Arnold Dwi Putra   | AX5  | 26  |      200.000 | 2026-10-01 | 2026-09 | 02444        | Transfer   | Online | Sulis   |                    |
| Selvy Tania        | B10  | 10  |      300.000 | 2026-10-01 | 2026-09 | 02447        | VA         | Online | Sulis   |                    |
| Dwi Hartono        | C6   | 33  |      250.000 | 2026-10-02 | 2026-09 | 02450        | Transfer   | Online | Sulis   |                    |
| Nova Diana         | B10  | 6   |      100.000 | 2026-10-02 | 2026-11 | 02452        | Transfer   | Online | Sulis   | Penambahan Tagihan |
| Novriyansyah Karna | AM1  | 11  |      300.000 | 2026-10-02 | 2026-09 | 02457        | Transfer   | Online | Sulis   |                    |
| Purnama Sari Djaja | E7   | 29  |      300.000 | 2026-10-03 | 2026-09 | 02480        | Transfer   | Online | Sulis   |                    |
| Sherly             | C11  | 30  |      250.000 | 2026-10-03 | 2026-09 | 02484        | Transfer   | Online | Sulis   |                    |
| Riyandi Susanto    | C11  | 28  |      250.000 | 2026-10-05 | 2026-09 | 02511        | Transfer   | Online | Sulis   |                    |
| Lusia Tungadi      | D8   | 11  |      350.000 | 2026-10-06 | 2026-09 | 02526        | Transfer   | Online | Sulis   |                    |
| Lilih Yuliani      | AX5  | 36  |      200.000 | 2026-10-06 | 2026-09 | 02531        | Transfer   | Online | Sulis   |                    |
| Andri Mustakim     | AX5  | 5   |      250.000 | 2026-10-07 | 2026-09 | 02547        | Transfer   | Online | Sulis   |                    |
