<?php

namespace Database\Seeders;

use App\Models\GuidedTour;
use App\Models\HelpSetting;
use App\Models\ManualBookSection;
use App\Models\User;
use Illuminate\Database\Seeder;

class HelpCenterSeeder extends Seeder
{
    private const INTERNAL_ROLES = [
        'root', 'super_admin', 'admin_estate', 'back_office', 'finance',
        'property_manager', 'operations_staff', 'security', 'technician',
        'vendor', 'loket', 'cs', 'collector',
    ];

    public function run(): void
    {
        $author = User::where('username', 'root')->first();

        HelpSetting::query()->updateOrCreate(
            ['scope_type' => 'global', 'scope_key' => null],
            ['is_enabled' => true, 'updated_by' => $author?->id]
        );
        HelpSetting::forgetCache();

        foreach ($this->sections() as $order => $section) {
            ManualBookSection::query()->updateOrCreate(
                ['module' => $section['module'], 'slug' => $section['slug']],
                [
                    ...$section,
                    'order' => $order,
                    'is_active' => true,
                    'created_by' => $author?->id,
                    'updated_by' => $author?->id,
                ]
            );
        }

        $this->guidedTours($author);
    }

    private function guidedTours(?User $author): void
    {
        $navTour = GuidedTour::query()->updateOrCreate(
            ['module' => 'general'],
            [
                'title' => 'Tur Navigasi Dasar',
                'description' => 'Kenali bagian utama aplikasi: menu sisi kiri, notifikasi, profil, dan tema tampilan.',
                'roles' => null,
                'is_active' => true,
                'auto_start' => true,
                'order' => 0,
                'created_by' => $author?->id,
                'updated_by' => $author?->id,
            ]
        );
        $navTour->steps()->delete();
        $navTour->steps()->createMany([
            ['target' => 'sidebar-menu', 'title' => 'Menu Navigasi', 'content' => 'Semua modul yang bisa Anda akses ada di sini. Daftar menu menyesuaikan otomatis dengan role dan hak akses Anda.', 'placement' => 'right', 'order' => 0],
            ['target' => 'page-title', 'title' => 'Judul & Bantuan Halaman', 'content' => 'Judul halaman yang sedang dibuka. Klik ikon info di sampingnya kapan saja untuk membaca panduan halaman ini.', 'placement' => 'bottom', 'order' => 1],
            ['target' => 'notifications-bell', 'title' => 'Notifikasi', 'content' => 'Pemberitahuan terbaru terkait tugas dan proses Anda muncul di sini.', 'placement' => 'bottom', 'order' => 2],
            ['target' => 'help-center-button', 'title' => 'Pusat Bantuan', 'content' => 'Kapan pun butuh bantuan, klik ikon ini untuk membuka Manual Book, memulai ulang tur, melihat FAQ, atau menghubungi admin.', 'placement' => 'bottom', 'order' => 3],
            ['target' => 'profile-menu', 'title' => 'Profil & Pengaturan', 'content' => 'Ubah profil, kata sandi, dan preferensi tampilan dari sini. Anda juga bisa logout dari menu ini.', 'placement' => 'bottom', 'order' => 4],
        ]);
    }

    private function sections(): array
    {
        return [
            // ---------- Umum (semua role) ----------
            [
                'module' => 'general', 'slug' => 'pengenalan-aplikasi', 'roles' => null,
                'title' => 'Pengenalan Aplikasi',
                'summary' => 'Apa itu Duta Indah Residence Estate Management dan siapa saja yang menggunakannya.',
                'content' => "Duta Indah Residence Estate Management adalah aplikasi untuk mengelola operasional perumahan: data penghuni dan unit, tagihan iuran, pembayaran, komplain, permintaan maintenance, dokumen, hingga portal mandiri untuk penghuni.\n\nAplikasi ini dipakai oleh dua kelompok pengguna:\n- Tim internal estate (admin, finance, loket, customer service, collector, teknisi, security, dan lainnya) lewat dashboard admin.\n- Penghuni/pemilik unit lewat portal khusus penghuni untuk melihat tagihan, membayar, dan mengajukan komplain atau permintaan layanan.\n\nSetiap akun hanya melihat menu dan data yang sesuai dengan role dan hak aksesnya masing-masing.",
                'tips' => ['Manual Book ini otomatis menyesuaikan isinya dengan role akun Anda saat login.'],
                'warnings' => [],
                'faqs' => [
                    ['question' => 'Kenapa menu saya berbeda dengan rekan kerja saya?', 'answer' => 'Menu dan panduan yang tampil mengikuti role dan hak akses (permission) akun masing-masing. Hubungi admin jika merasa ada akses yang seharusnya Anda miliki.'],
                ],
            ],
            [
                'module' => 'general', 'slug' => 'navigasi-dasar', 'roles' => null,
                'title' => 'Navigasi Dasar',
                'summary' => 'Mengenal sidebar, header, notifikasi, dan cara berpindah tema.',
                'content' => "Tata letak aplikasi terdiri dari:\n- Sidebar kiri: daftar menu sesuai hak akses Anda. Bisa dilipat lewat tombol di header.\n- Header atas: judul halaman, pemilih tema (Light/Dark/System), lonceng notifikasi, dan menu profil.\n- Konten utama: isi halaman yang sedang dibuka.\n\nDi perangkat mobile atau tablet, sidebar berubah menjadi menu geser (drawer) yang dibuka lewat ikon garis tiga di pojok kiri atas.",
                'steps' => [
                    ['title' => 'Buka menu', 'description' => 'Klik salah satu item di sidebar untuk berpindah halaman.'],
                    ['title' => 'Ganti tema', 'description' => 'Gunakan dropdown tema di header untuk beralih antara Light, Dark, atau mengikuti pengaturan sistem perangkat.'],
                    ['title' => 'Cek notifikasi', 'description' => 'Klik ikon lonceng untuk melihat pemberitahuan terbaru.'],
                ],
                'tips' => ['Tampilan sudah responsif — semua fitur tetap bisa diakses dari HP maupun tablet.'],
                'warnings' => [],
                'faqs' => [],
            ],
            [
                'module' => 'general', 'slug' => 'pencarian-dan-filter', 'roles' => null,
                'title' => 'Cara Pencarian dan Filter',
                'summary' => 'Pola pencarian dan filter yang konsisten dipakai di hampir semua halaman daftar data.',
                'content' => "Hampir setiap halaman daftar (Penghuni, Unit, Tagihan, Pembayaran, dst.) punya kotak pencarian dan filter di bagian atas tabel.\n\nKetik kata kunci di kotak pencarian (nama, ID, nomor telepon, dsb. tergantung halaman) untuk mempersempit hasil secara otomatis. Gunakan dropdown filter di sebelahnya untuk menyaring berdasarkan status, role, periode, atau kategori lain sesuai halaman yang dibuka.",
                'tips' => ['Filter dan pencarian bisa dipakai bersamaan.', 'Klik tombol Refresh di pojok kanan atas untuk memuat ulang data terbaru.'],
                'warnings' => [],
                'faqs' => [
                    ['question' => 'Kenapa data yang saya cari tidak muncul?', 'answer' => 'Periksa apakah ada filter aktif (misal status atau periode) yang menyembunyikan data tersebut. Coba kosongkan filter lalu cari ulang.'],
                ],
            ],
            [
                'module' => 'general', 'slug' => 'profil-dan-kata-sandi', 'roles' => null,
                'title' => 'Ubah Profil dan Kata Sandi',
                'summary' => 'Cara memperbarui data akun dan mengganti password.',
                'content' => 'Untuk tim internal: buka menu Profil untuk melihat data akun, dan menu Ganti Password untuk mengubah kata sandi (perlu memasukkan password lama). Untuk penghuni: kedua pengaturan ini digabung di menu Akun/Pengaturan pada portal penghuni, termasuk preferensi tema dan bahasa.',
                'steps' => [
                    ['title' => 'Buka menu Profil (staff) atau Akun (penghuni)', 'description' => 'Menu ini tersedia lewat dropdown foto profil di header.'],
                    ['title' => 'Ganti Password', 'description' => 'Masukkan password lama dan password baru, lalu simpan.'],
                ],
                'tips' => [],
                'warnings' => ['Gunakan password yang tidak mudah ditebak dan jangan bagikan ke orang lain.'],
                'faqs' => [
                    ['question' => 'Saya lupa password, harus bagaimana?', 'answer' => 'Hubungi admin untuk reset password. Setelah direset, password sementara adalah "password" — segera ganti setelah login.'],
                ],
            ],
            [
                'module' => 'general', 'slug' => 'faq-kendala-umum', 'roles' => null,
                'title' => 'FAQ & Kendala Umum',
                'summary' => 'Pertanyaan yang sering ditanyakan dan solusi kendala umum.',
                'content' => 'Kumpulan pertanyaan umum lintas modul. Untuk pertanyaan spesifik suatu modul, buka panduan modul terkait.',
                'faqs' => [
                    ['question' => 'Kenapa saya tidak bisa login?', 'answer' => 'Pastikan username dan password benar. Jika akun dinonaktifkan admin, Anda akan melihat pesan khusus saat mencoba login — hubungi admin untuk mengaktifkan kembali.'],
                    ['question' => 'Kenapa tombol Tambah/Edit/Hapus tidak muncul di suatu halaman?', 'answer' => 'Tombol aksi hanya muncul jika akun Anda punya permission yang sesuai. Ini bukan bug — hubungi admin bila Anda memang seharusnya punya akses tersebut.'],
                    ['question' => 'Data yang baru saya ubah belum muncul, kenapa?', 'answer' => 'Coba klik tombol Refresh di halaman tersebut. Beberapa data (misalnya di portal penghuni) juga otomatis diperbarui berkala tanpa perlu refresh manual.'],
                ],
            ],

            // ---------- Dashboard (internal) ----------
            [
                'module' => 'dashboard', 'slug' => 'ringkasan-dashboard', 'roles' => self::INTERNAL_ROLES,
                'title' => 'Dashboard Utama',
                'summary' => 'Ringkasan yang tampil di halaman Dashboard sesuai hak akses Anda.',
                'content' => 'Dashboard menampilkan ringkasan angka penting: jumlah penghuni/unit, status tagihan, pembayaran terbaru, dan grafik terkait laporan, sesuai permission yang Anda miliki. Widget yang memerlukan permission tertentu (misalnya laporan keuangan) hanya muncul untuk role yang berwenang.',
                'tips' => ['Gunakan menu Laporan untuk melihat data lebih rinci daripada yang ditampilkan ringkas di dashboard.'],
                'warnings' => [],
                'faqs' => [],
            ],

            // ---------- Penghuni ----------
            [
                'module' => 'residents', 'slug' => 'kelola-data-penghuni',
                'roles' => ['root', 'super_admin', 'admin_estate', 'back_office', 'cs'],
                'title' => 'Kelola Data Penghuni',
                'summary' => 'Cara menambah, mengubah, mencari, dan menghapus data penghuni.',
                'content' => 'Menu Penghuni menyimpan data pemilik/penghuni unit. Setiap penghuni baru otomatis mendapat akun login portal (role customer) yang bisa dipakai untuk melihat tagihan dan mengajukan komplain, begitu unit-nya sudah tersedia.',
                'steps' => [
                    ['title' => 'Tambah penghuni', 'description' => 'Klik "Tambah Penghuni", isi data (nama wajib, kontak dan identitas opsional), lalu Simpan. Username dan password sementara akun login akan ditampilkan setelah berhasil disimpan — sampaikan ke penghuni terkait.'],
                    ['title' => 'Lihat detail', 'description' => 'Klik baris data atau pilih Detail dari menu titik tiga untuk membuka halaman lengkap penghuni beserta unit, tagihan, transaksi, dan riwayat lainnya.'],
                    ['title' => 'Ubah data', 'description' => 'Gunakan tombol Edit pada baris tabel atau di halaman Detail Penghuni. Nomor HP dan email diperiksa otomatis supaya tidak bentrok dengan data penghuni lain.'],
                    ['title' => 'Hapus penghuni', 'description' => 'Hanya bisa dilakukan jika penghuni tidak lagi memiliki unit aktif.'],
                ],
                'tips' => ['Nomor HP dan email divalidasi langsung saat mengetik — jika sudah dipakai penghuni lain, akan muncul peringatan sebelum Anda klik Simpan.'],
                'warnings' => ['Akun login penghuni baru belum bisa dipakai penuh sebelum unit miliknya dibuat/ditautkan.'],
                'faqs' => [
                    ['question' => 'Kenapa saat edit data penghuni muncul error "sudah terdaftar" padahal itu data miliknya sendiri?', 'answer' => 'Ini sudah diperbaiki — validasi sekarang mengecualikan data milik penghuni yang sedang diedit. Jika masih terjadi, refresh halaman dan coba lagi.'],
                ],
            ],
            [
                'module' => 'residents', 'slug' => 'detail-penghuni-tab',
                'roles' => self::INTERNAL_ROLES,
                'title' => 'Memahami Halaman Detail Penghuni',
                'summary' => 'Penjelasan tab-tab di halaman Detail Penghuni.',
                'content' => "Halaman Detail Penghuni terbagi menjadi beberapa tab: Ringkasan (angka tagihan/pembayaran), Data Penghuni (identitas & akun login), Data Unit (properti yang dimiliki), Tagihan, Transaksi, Komplain, Layanan, Kunjungan Collector, Janji Pembayaran, Dokumen, Kendaraan, Penghuni Tambahan & Petugas, Surat & Notifikasi, dan Riwayat Aktivitas.\n\nJika penghuni memiliki lebih dari satu unit, gunakan pemilih unit di tab Data Unit untuk melihat seluruh unit atau fokus ke salah satunya — pilihan ini akan mempengaruhi data yang ditampilkan di tab-tab lain juga.",
                'tips' => [],
                'warnings' => [],
                'faqs' => [],
            ],

            // ---------- Unit ----------
            [
                'module' => 'units', 'slug' => 'kelola-unit',
                'roles' => ['root', 'super_admin', 'admin_estate', 'back_office'],
                'title' => 'Kelola Data Unit',
                'summary' => 'Cara menambah unit, mengubah data, dan memindahkan kepemilikan.',
                'content' => 'Menu Unit Rumah menyimpan data properti (kavling/bangunan) beserta pemiliknya. Mengubah pemilik unit (field Pemilik) akan otomatis menyesuaikan akun login penghuni terkait — akun pemilik lama dilepas dari unit tersebut, dan akun pemilik baru ditautkan.',
                'steps' => [
                    ['title' => 'Tambah unit', 'description' => 'Isi ID unit, pilih Pemilik (penghuni), Cluster, Blok, Kavling, dan tipe properti, lalu Simpan.'],
                    ['title' => 'Ubah pemilik unit', 'description' => 'Buka Edit pada unit, ganti field Pemilik ke penghuni yang benar, lalu Simpan.'],
                    ['title' => 'Konversi properti', 'description' => 'Kavling developer dapat dikonversi menjadi bangunan lewat tombol Konversi Properti setelah serah terima.'],
                ],
                'tips' => ['Setelah pemilik unit diubah, data tagihan/pembayaran/komplain di portal penghuni ikut menyesuaikan otomatis tanpa perlu langkah tambahan.'],
                'warnings' => ['Pastikan memilih penghuni yang benar sebelum menyimpan — riwayat transaksi unit tetap mengikuti unit tersebut, bukan pemiliknya.'],
                'faqs' => [],
            ],

            // ---------- Billing ----------
            [
                'module' => 'billings', 'slug' => 'proses-tagihan',
                'roles' => ['root', 'super_admin', 'admin_estate', 'back_office', 'finance'],
                'title' => 'Proses Tagihan',
                'summary' => 'Cara membuat tagihan bulanan, khusus, mundur, dan approval-nya.',
                'content' => 'Menu Tagihan digunakan untuk men-generate iuran bulanan seluruh unit, membuat tagihan khusus per unit, atau tagihan mundur (back billing). Tagihan yang sudah dibuat perlu di-approve sebelum bisa dibayar oleh penghuni.',
                'steps' => [
                    ['title' => 'Generate tagihan bulanan', 'description' => 'Pilih periode, lalu jalankan proses generate — sistem membuat tagihan untuk seluruh unit aktif sesuai tarif cluster masing-masing.'],
                    ['title' => 'Buat tagihan khusus', 'description' => 'Tersedia juga dari halaman Detail Penghuni, untuk kebutuhan di luar iuran rutin (denda, biaya tambahan, dsb.).'],
                    ['title' => 'Approve tagihan', 'description' => 'Buka daftar "Menunggu Approval", periksa, lalu setujui satu per satu atau sekaligus (approve batch).'],
                ],
                'tips' => [],
                'warnings' => ['Tagihan yang sudah di-approve akan langsung terlihat oleh penghuni di portal mereka.'],
                'faqs' => [],
            ],

            // ---------- Piutang / Receivables ----------
            [
                'module' => 'receivables', 'slug' => 'monitoring-piutang',
                'roles' => ['root', 'super_admin', 'admin_estate', 'back_office', 'finance', 'property_manager'],
                'title' => 'Monitoring Piutang',
                'summary' => 'Cara membaca daftar tagihan belum lunas, aging piutang, dan tier denda tunggakan.',
                'content' => "Menu Piutang menampilkan seluruh tagihan yang belum lunas (status Belum Bayar maupun Sebagian) beserta denda tunggakannya, dihitung ulang secara real-time setiap kali halaman dibuka. Bedanya dengan menu Tagihan: menu Tagihan menampilkan semua tagihan termasuk yang sudah lunas, sedangkan menu ini fokus khusus pada apa yang masih harus ditagih ke penghuni.\n\nDenda dihitung otomatis berjenjang berdasarkan umur tunggakan tiap tagihan (bukan diakumulasi tiap bulan): tagihan bulan berjalan tidak kena denda, tunggakan 1-2 bulan kena Rp15.000, dan tunggakan 3 bulan atau lebih kena Rp30.000 — nilainya tetap Rp30.000 meski tunggakan sudah lebih dari 3 bulan.",
                'steps' => [
                    ['title' => 'Baca ringkasan tier denda', 'description' => 'Tiga kartu di bagian atas (Bulan Berjalan, Tunggakan 1-2 Bulan, Tunggakan 3 Bulan+) menjumlahkan total tagihan pada tiap tingkatan denda, supaya cepat lihat seberapa besar risiko tunggakan lama.'],
                    ['title' => 'Baca ringkasan aging', 'description' => 'Empat kartu di bawahnya (< 30 hari, 30-60 hari, 60-90 hari, > 90 hari) menunjukkan sebaran nilai piutang berdasarkan lama hari sejak periode tagihan dimulai.'],
                    ['title' => 'Filter per unit atau status', 'description' => 'Gunakan kolom ID Unit atau dropdown Status untuk mempersempit daftar. Secara default hanya tagihan berstatus Belum Bayar atau Sebagian yang ditampilkan.'],
                    ['title' => 'Baca detail per tagihan', 'description' => 'Kolom Umur Tunggakan, Denda, Total, dan Sisa Tagihan pada tabel dihitung otomatis dan selalu konsisten dengan angka yang tampil di menu Tagihan.'],
                ],
                'tips' => ['Nilai denda di halaman ini otomatis bertambah begitu tunggakan memasuki tier bulan berikutnya — tidak perlu proses atau tombol tambahan untuk memicunya.'],
                'warnings' => ['Halaman ini khusus untuk monitoring. Untuk memproses pembayaran gunakan menu Pembayaran, dan untuk memberi keringanan denda atau diskon gunakan menu Tagihan.'],
                'faqs' => [
                    ['question' => 'Kenapa nominal denda bertambah padahal saya tidak mengubah apa pun?', 'answer' => 'Itu wajar. Denda dihitung otomatis dari umur tunggakan saat ini, jadi begitu tagihan bertambah tua dan melewati batas tier berikutnya (1-2 bulan atau 3 bulan+), dendanya ikut naik otomatis tanpa perlu ada perubahan manual dari admin.'],
                    ['question' => 'Kenapa jumlah tagihan di Piutang berbeda dengan menu Tagihan?', 'answer' => 'Menu Piutang secara default hanya menampilkan tagihan yang belum lunas (Belum Bayar/Sebagian), sedangkan menu Tagihan menampilkan semua tagihan termasuk yang sudah Lunas atau Dibatalkan.'],
                ],
            ],

            // ---------- Payments ----------
            [
                'module' => 'payments', 'slug' => 'proses-pembayaran',
                'roles' => ['root', 'super_admin', 'admin_estate', 'back_office', 'finance', 'loket', 'collector'],
                'title' => 'Proses Pembayaran',
                'summary' => 'Cara memproses pembayaran loket, verifikasi manual, dan pembayaran gateway.',
                'content' => 'Menu Pembayaran menangani pembayaran tunai/loket, pembayaran online lewat gateway (Xendit/Midtrans), dan verifikasi bukti transfer manual dari penghuni.',
                'steps' => [
                    ['title' => 'Cari tagihan', 'description' => 'Gunakan pencarian di menu Pembayaran untuk menemukan tagihan yang akan dibayar.'],
                    ['title' => 'Proses pembayaran loket', 'description' => 'Pilih metode pembayaran, masukkan nominal, lalu proses — kuitansi otomatis dapat dicetak.'],
                    ['title' => 'Verifikasi bukti manual', 'description' => 'Untuk pembayaran manual dari portal penghuni, buka daftar transaksi berstatus menunggu verifikasi, periksa bukti transfer, lalu Verifikasi atau Tolak.'],
                ],
                'tips' => [],
                'warnings' => ['Pembayaran yang sudah diverifikasi tidak dapat dibatalkan langsung — gunakan menu Reversal jika terjadi kesalahan.'],
                'faqs' => [],
            ],

            // ---------- Users ----------
            [
                'module' => 'users', 'slug' => 'kelola-user',
                'roles' => ['root', 'super_admin', 'admin_estate'],
                'title' => 'Kelola User & Akses',
                'summary' => 'Cara menambah user staff/customer, mengatur role, dan reset password.',
                'content' => 'Menu User mengelola seluruh akun login: staff internal maupun akun customer (portal penghuni). Setiap user memiliki satu role yang menentukan menu dan permission yang bisa diakses.',
                'steps' => [
                    ['title' => 'Tambah user', 'description' => 'Isi nama, username, role, dan password awal. Untuk role customer, pilih Unit/Penghuni yang akan ditautkan.'],
                    ['title' => 'Reset password', 'description' => 'Gunakan menu titik tiga pada baris user lalu pilih Reset Password — password sementara akan ditampilkan sekali.'],
                    ['title' => 'Nonaktifkan/aktifkan user', 'description' => 'Gunakan toggle status untuk menonaktifkan akun tanpa menghapus datanya.'],
                ],
                'tips' => ['Akun customer yang dibuat lewat menu Tambah Penghuni otomatis muncul di sini juga.'],
                'warnings' => ['Menghapus user bersifat permanen untuk riwayat aktivitasnya — pertimbangkan menonaktifkan saja jika masih diperlukan.'],
                'faqs' => [],
            ],

            // ---------- Portal Penghuni ----------
            [
                'module' => 'resident-portal', 'slug' => 'panduan-penghuni',
                'roles' => ['customer'],
                'title' => 'Panduan Portal Penghuni',
                'summary' => 'Cara melihat tagihan, membayar, komplain, dan mengajukan layanan lewat portal.',
                'content' => "Portal penghuni memudahkan Anda memantau dan mengurus keperluan unit tanpa perlu datang ke kantor pengelola.\n\nMenu yang tersedia: Dashboard (ringkasan), Properti (daftar unit milik Anda), Tagihan, Pembayaran, Metode Bayar, Komplain, Maintenance, Dokumen, Notifikasi, Aktivitas, dan Pengaturan.",
                'steps' => [
                    ['title' => 'Lihat tagihan', 'description' => 'Buka menu Tagihan untuk melihat semua invoice, status (belum bayar/lunas/jatuh tempo), dan detailnya.'],
                    ['title' => 'Bayar tagihan', 'description' => 'Buka detail tagihan lalu pilih metode pembayaran. Untuk transfer manual, unggah bukti transfer setelah membayar.'],
                    ['title' => 'Ajukan komplain', 'description' => 'Buka menu Komplain, klik Buat Komplain, isi judul dan deskripsi, lalu kirim. Anda bisa memantau status dan menambah komentar di sana.'],
                    ['title' => 'Ajukan permintaan maintenance', 'description' => 'Sama seperti komplain, lewat menu Maintenance — sertakan kategori dan jadwal yang diinginkan.'],
                    ['title' => 'Unduh dokumen', 'description' => 'Menu Dokumen berisi invoice dan kuitansi yang bisa diunduh kapan saja.'],
                ],
                'tips' => ['Menu Properti menampilkan semua unit yang Anda miliki jika lebih dari satu.', 'Halaman utama portal memperbarui data secara berkala — tidak perlu refresh manual.'],
                'warnings' => [],
                'faqs' => [
                    ['question' => 'Kenapa unit saya belum muncul di portal?', 'answer' => 'Akun Anda baru bisa melihat data unit setelah admin estate menautkan/membuat data unit untuk Anda. Hubungi customer service jika sudah lama menunggu.'],
                    ['question' => 'Bagaimana cara ganti password?', 'answer' => 'Buka menu Pengaturan di portal, lalu ubah kata sandi Anda dari sana.'],
                ],
            ],
        ];
    }
}
