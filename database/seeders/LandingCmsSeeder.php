<?php

namespace Database\Seeders;

use App\Models\HeroSlide;
use App\Models\LandingAboutSetting;
use App\Models\LandingArticle;
use App\Models\LandingContactSetting;
use App\Models\LandingContentCategory;
use App\Models\LandingEvent;
use App\Models\LandingFaq;
use App\Models\LandingFeature;
use App\Models\LandingFooterSetting;
use App\Models\LandingGalleryAlbum;
use App\Models\LandingHeaderSetting;
use App\Models\LandingPartner;
use App\Models\LandingSeoSetting;
use App\Models\LandingService;
use App\Models\LandingSocialLink;
use App\Models\LandingStatistic;
use App\Models\LandingTestimonial;
use App\Models\NavMenuItem;
use App\Models\SiteSetting;
use App\Services\MediaProcessingService;
use Illuminate\Database\Seeder;

/**
 * Seeds realistic first-run content for the Landing Page CMS, reusing the
 * exact copy already written for the static landing page
 * (frontend/src/constants/landingContent.js) so the CMS and the page start
 * in sync, plus new content for the sections that didn't exist before this
 * feature (Events, Gallery, FAQ). Images are fetched from the same Unsplash
 * URLs the static page used to use, processed through the real media
 * pipeline (MediaProcessingService) so the CMS media library isn't empty.
 */
class LandingCmsSeeder extends Seeder
{
    private MediaProcessingService $media;

    public function run(): void
    {
        $this->media = app(MediaProcessingService::class);

        $this->seedSiteAndHeader();
        $this->seedAbout();
        $this->seedContactAndFooterAndSeo();
        $this->seedNavAndSocial();
        $categories = $this->seedCategories();
        $this->seedHeroSlides();
        $this->seedServices($categories);
        $this->seedFeatures();
        $this->seedStatistics();
        $this->seedTestimonials();
        $this->seedPartners();
        $this->seedFaqs($categories);
        $this->seedEvents();
        $this->seedArticles();
        $this->seedGallery($categories);
    }

    private function image(string $url, ?string $alt = null): ?int
    {
        return $this->media->storeFromUrl($url, null, $alt)?->id;
    }

    private function seedSiteAndHeader(): void
    {
        SiteSetting::query()->firstOrCreate([], [
            'site_name' => 'Grand Duta Estate Management',
            'default_theme' => 'system',
            'primary_color' => '#0f766e',
            'secondary_color' => '#f59e0b',
            'default_language' => 'id',
            'maintenance_mode' => false,
        ]);

        LandingHeaderSetting::query()->firstOrCreate([], [
            'site_name' => 'Grand Duta',
            'sticky_enabled' => true,
            'show_login_button' => true,
            'login_button_text' => 'Login',
            'cta_button_enabled' => false,
        ]);
    }

    private function seedAbout(): void
    {
        LandingAboutSetting::query()->firstOrCreate([], [
            'title' => 'Pengelola Kawasan yang Mengutamakan Kenyamanan Anda',
            'description' => 'Grand Duta Estate Management adalah sistem pengelolaan kawasan yang membantu pengelola properti — perumahan, apartemen, maupun cluster — dalam menjalankan operasional sehari-hari secara lebih rapi, cepat, dan transparan. Kami berkomitmen menghadirkan pengalaman tinggal yang aman dan nyaman bagi seluruh penghuni.',
            'image_media_id' => $this->image('https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=900&q=70', 'Tim pengelola kawasan'),
            'pillars' => [
                ['icon' => 'SafetyCertificateOutlined', 'title' => 'Keamanan', 'description' => 'Pengawasan kawasan 24 jam, buku tamu digital, dan sinyal darurat yang terhubung langsung ke petugas.'],
                ['icon' => 'SmileOutlined', 'title' => 'Kenyamanan', 'description' => 'Fasilitas terawat dan proses layanan penghuni yang cepat, tanpa antrean panjang.'],
                ['icon' => 'EyeOutlined', 'title' => 'Transparansi', 'description' => 'Riwayat tagihan, pembayaran, dan pengaduan dapat dipantau kapan saja secara real-time.'],
            ],
        ]);
    }

    private function seedContactAndFooterAndSeo(): void
    {
        LandingContactSetting::query()->firstOrCreate([], [
            'address' => 'Jl. Grand Duta Raya No. 1, Tangerang, Banten 15810',
            'phone' => '(021) 555-0100',
            'whatsapp' => '+62 812-3456-7890',
            'email' => 'info@grandduta-estate.id',
            'maps_embed_url' => 'https://www.google.com/maps?q=Tangerang%2C+Banten&output=embed',
            'business_hours' => [['label' => 'Senin - Sabtu', 'value' => '08.00 - 17.00 WIB']],
        ]);

        LandingFooterSetting::query()->firstOrCreate([], [
            'description' => 'Sistem pengelolaan kawasan modern untuk perumahan, apartemen, dan cluster — menghadirkan layanan yang aman, transparan, dan mudah diakses.',
            'copyright_text' => 'Grand Duta Estate Management. Seluruh hak cipta dilindungi.',
            'show_social_links' => true,
            'show_quick_links' => true,
        ]);

        LandingSeoSetting::query()->firstOrCreate([], [
            'meta_title' => 'Grand Duta Estate Management',
            'meta_description' => 'Kelola kawasan Anda lebih modern, aman, dan terintegrasi bersama Grand Duta Estate Management.',
            'og_title' => 'Grand Duta Estate Management',
            'og_description' => 'Satu platform untuk pengelolaan pembayaran, keamanan, pengaduan, fasilitas, hingga informasi penghuni.',
            'twitter_card_type' => 'summary_large_image',
        ]);
    }

    private function seedNavAndSocial(): void
    {
        $links = [
            ['label' => 'Beranda', 'url' => '#beranda'],
            ['label' => 'Tentang Kami', 'url' => '#tentang'],
            ['label' => 'Layanan', 'url' => '#layanan'],
            ['label' => 'Fasilitas', 'url' => '#fasilitas'],
            ['label' => 'Berita & Event', 'url' => '#berita'],
            ['label' => 'Galeri', 'url' => '#galeri'],
            ['label' => 'Mitra', 'url' => '#mitra'],
            ['label' => 'FAQ', 'url' => '#faq'],
            ['label' => 'Kontak', 'url' => '#kontak'],
        ];

        foreach ($links as $index => $link) {
            NavMenuItem::query()->firstOrCreate(['label' => $link['label']], [
                'url' => $link['url'],
                'open_in_new_tab' => false,
                'show_in_footer' => true,
                'order' => $index,
                'is_active' => true,
            ]);
        }

        $socials = [
            ['platform' => 'facebook', 'url' => 'https://facebook.com'],
            ['platform' => 'instagram', 'url' => 'https://instagram.com'],
            ['platform' => 'twitter_x', 'url' => 'https://x.com'],
        ];

        foreach ($socials as $index => $social) {
            LandingSocialLink::query()->firstOrCreate(['platform' => $social['platform']], [
                'url' => $social['url'],
                'order' => $index,
                'is_active' => true,
            ]);
        }
    }

    /** @return array<string, LandingContentCategory> keyed by "group:slug" */
    private function seedCategories(): array
    {
        $definitions = [
            ['group' => 'service', 'name' => 'Layanan', 'slug' => 'layanan'],
            ['group' => 'service', 'name' => 'Fasilitas', 'slug' => 'fasilitas'],
            ['group' => 'faq', 'name' => 'Pembayaran', 'slug' => 'pembayaran'],
            ['group' => 'faq', 'name' => 'Umum', 'slug' => 'umum'],
            ['group' => 'gallery', 'name' => 'Kawasan', 'slug' => 'kawasan'],
        ];

        $categories = [];
        foreach ($definitions as $index => $definition) {
            $category = LandingContentCategory::query()->firstOrCreate(
                ['group' => $definition['group'], 'slug' => $definition['slug']],
                ['name' => $definition['name'], 'order' => $index, 'is_active' => true],
            );
            $categories["{$definition['group']}:{$definition['slug']}"] = $category;
        }

        return $categories;
    }

    private function seedHeroSlides(): void
    {
        HeroSlide::query()->firstOrCreate(['title' => 'Kelola Kawasan Anda Lebih Modern, Aman, dan Terintegrasi'], [
            'subtitle' => 'Estate Management System',
            'description' => 'Satu platform untuk pengelolaan pembayaran, keamanan, pengaduan, fasilitas, hingga informasi penghuni — memudahkan pengelola dan penghuni kawasan dalam satu genggaman.',
            'background_media_id' => $this->image('https://images.unsplash.com/photo-1580587771525-78b9dba3b914?auto=format&fit=crop&w=1000&q=70', 'Kawasan perumahan modern'),
            'cta_text' => 'Pelajari Lebih Lanjut',
            'cta_url' => '#tentang',
            'cta_target' => '_self',
            'order' => 0,
            'is_active' => true,
        ]);
    }

    private function seedServices(array $categories): void
    {
        $layanan = $categories['service:layanan']->id;
        $fasilitas = $categories['service:fasilitas']->id;

        $services = [
            ['icon' => 'WalletOutlined', 'title' => 'Pembayaran Tagihan', 'description' => 'Bayar iuran dan tagihan bulanan secara online dengan riwayat pembayaran yang jelas.'],
            ['icon' => 'HomeOutlined', 'title' => 'Informasi Penghuni & Unit', 'description' => 'Data penghuni dan unit tercatat rapi serta mudah diakses oleh pihak yang berwenang.'],
            ['icon' => 'MessageOutlined', 'title' => 'Pengaduan & Keluhan', 'description' => 'Sampaikan keluhan seputar kawasan dan pantau status penanganannya sampai selesai.'],
            ['icon' => 'AlertOutlined', 'title' => 'Emergency / Panic Button', 'description' => 'Kirim sinyal darurat beserta lokasi Anda kepada petugas keamanan hanya dalam sekali tekan.'],
            ['icon' => 'CalendarOutlined', 'title' => 'Booking Fasilitas', 'description' => 'Pesan clubhouse, lapangan, atau ruang pertemuan secara online tanpa perlu datang ke kantor.'],
            ['icon' => 'SafetyOutlined', 'title' => 'Informasi Keamanan', 'description' => 'Pantau aktivitas keamanan kawasan dan laporan kejadian secara transparan.'],
            ['icon' => 'TeamOutlined', 'title' => 'Pengelolaan Tamu', 'description' => 'Daftarkan tamu Anda sebelum berkunjung agar proses verifikasi di gerbang lebih cepat.'],
            ['icon' => 'FileTextOutlined', 'title' => 'Surat & Dokumen Penghuni', 'description' => 'Ajukan dan unduh surat pengantar maupun dokumen kependudukan secara digital.'],
            ['icon' => 'NotificationOutlined', 'title' => 'Pengumuman Kawasan', 'description' => 'Dapatkan informasi terbaru seputar kawasan langsung melalui aplikasi.'],
            ['icon' => 'CarOutlined', 'title' => 'Layanan Collector & Petugas', 'description' => 'Petugas lapangan tercatat dan terpantau untuk memastikan pelayanan yang akuntabel.'],
        ];

        foreach ($services as $index => $service) {
            LandingService::query()->firstOrCreate(['title' => $service['title']], [
                'icon' => $service['icon'],
                'description' => $service['description'],
                'category_id' => $layanan,
                'order' => $index,
                'is_active' => true,
            ]);
        }

        $facilities = [
            ['title' => 'Clubhouse', 'img' => 'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?auto=format&fit=crop&w=600&q=60'],
            ['title' => 'Kolam Renang', 'img' => 'https://images.unsplash.com/photo-1519046904884-53103b34b206?auto=format&fit=crop&w=600&q=60'],
            ['title' => 'Taman', 'img' => 'https://images.unsplash.com/photo-1585320806297-9794b3e4eeae?auto=format&fit=crop&w=600&q=60'],
            ['title' => 'Tempat Ibadah', 'img' => 'https://images.unsplash.com/photo-1519817650390-64a93db51149?auto=format&fit=crop&w=600&q=60'],
            ['title' => 'Lapangan Olahraga', 'img' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=600&q=60'],
            ['title' => 'Area Komersial', 'img' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=600&q=60'],
            ['title' => 'Keamanan 24 Jam', 'img' => 'https://images.unsplash.com/photo-1517646287270-a5a9ca602e5c?auto=format&fit=crop&w=600&q=60'],
            ['title' => 'Area Parkir', 'img' => 'https://images.unsplash.com/photo-1470224114660-3f6686c562eb?auto=format&fit=crop&w=600&q=60'],
            ['title' => 'Ruang Pertemuan', 'img' => 'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=600&q=60'],
        ];

        foreach ($facilities as $index => $facility) {
            LandingService::query()->firstOrCreate(['title' => $facility['title']], [
                'image_media_id' => $this->image($facility['img'], "Fasilitas {$facility['title']}"),
                'category_id' => $fasilitas,
                'order' => 100 + $index,
                'is_active' => true,
            ]);
        }
    }

    private function seedFeatures(): void
    {
        $features = [
            ['icon' => 'ClusterOutlined', 'title' => 'Pengelolaan Terintegrasi', 'description' => 'Seluruh proses kawasan berjalan dalam satu sistem yang terhubung.'],
            ['icon' => 'CreditCardOutlined', 'title' => 'Pembayaran Mudah & Transparan', 'description' => 'Berbagai metode pembayaran dengan bukti dan riwayat yang jelas.'],
            ['icon' => 'ThunderboltOutlined', 'title' => 'Respons Pengaduan Lebih Cepat', 'description' => 'Setiap laporan langsung diteruskan ke petugas yang bertanggung jawab.'],
            ['icon' => 'SafetyCertificateOutlined', 'title' => 'Keamanan Kawasan Lebih Baik', 'description' => 'Sinyal darurat, buku tamu, dan patroli terpantau secara digital.'],
            ['icon' => 'DashboardOutlined', 'title' => 'Informasi Real-time', 'description' => 'Status tagihan, pengaduan, dan pengumuman selalu ter-update.'],
            ['icon' => 'MobileOutlined', 'title' => 'Akses Website & Mobile', 'description' => 'Kelola kebutuhan kawasan kapan saja lewat browser maupun aplikasi.'],
            ['icon' => 'DatabaseOutlined', 'title' => 'Data Terorganisasi', 'description' => 'Data penghuni dan unit tersimpan rapi, akurat, dan mudah dicari.'],
        ];

        foreach ($features as $index => $feature) {
            LandingFeature::query()->firstOrCreate(['title' => $feature['title']], [
                'icon' => $feature['icon'],
                'description' => $feature['description'],
                'order' => $index,
                'is_active' => true,
            ]);
        }
    }

    private function seedStatistics(): void
    {
        $stats = [
            ['label' => 'Unit Terkelola', 'source' => 'units_count', 'value' => 1240, 'suffix' => '+', 'icon' => 'HomeOutlined'],
            ['label' => 'Keamanan', 'source' => 'manual', 'value' => 24, 'suffix' => '/7', 'icon' => 'SafetyCertificateOutlined'],
            ['label' => 'Kepuasan Penghuni', 'source' => 'manual', 'value' => 98, 'suffix' => '%', 'icon' => 'SmileOutlined'],
        ];

        foreach ($stats as $index => $stat) {
            LandingStatistic::query()->firstOrCreate(['label' => $stat['label']], [
                'value' => $stat['value'],
                'suffix' => $stat['suffix'],
                'icon' => $stat['icon'],
                'source' => $stat['source'],
                'order' => $index,
                'is_active' => true,
            ]);
        }
    }

    private function seedTestimonials(): void
    {
        $testimonials = [
            ['name' => 'Ibu Ratna Wulandari', 'position' => 'Penghuni Cluster Anggrek', 'content' => 'Bayar iuran bulanan sekarang jauh lebih praktis, tinggal buka aplikasi tanpa perlu antre ke kantor pengelola.', 'rating' => 5],
            ['name' => 'Bapak Hendra Saputra', 'position' => 'Penghuni Cluster Melati', 'content' => 'Pengaduan soal lampu jalan yang mati direspons cepat, statusnya juga bisa saya pantau langsung dari aplikasi.', 'rating' => 5],
            ['name' => 'Ibu Siti Aminah', 'position' => 'Penghuni Cluster Kenanga', 'content' => 'Fitur booking clubhouse sangat membantu untuk acara keluarga, prosesnya cepat dan tidak ribet.', 'rating' => 4],
        ];

        foreach ($testimonials as $index => $testimonial) {
            LandingTestimonial::query()->firstOrCreate(['name' => $testimonial['name']], [
                'position' => $testimonial['position'],
                'content' => $testimonial['content'],
                'rating' => $testimonial['rating'],
                'order' => $index,
                'is_active' => true,
            ]);
        }
    }

    private function seedPartners(): void
    {
        $partners = ['Bank Mitra Sejahtera', 'Waskita Keamanan', 'CleanPro Facility', 'GreenScape Landscaping', 'SecureNet CCTV', 'PowerLine Utility'];

        foreach ($partners as $index => $name) {
            LandingPartner::query()->firstOrCreate(['name' => $name], [
                'order' => $index,
                'is_active' => true,
            ]);
        }
    }

    private function seedFaqs(array $categories): void
    {
        $pembayaran = $categories['faq:pembayaran']->id;
        $umum = $categories['faq:umum']->id;

        $faqs = [
            ['category_id' => $pembayaran, 'question' => 'Bagaimana cara membayar iuran bulanan?', 'answer' => 'Login ke aplikasi, buka menu Tagihan, pilih tagihan yang ingin dibayar, lalu ikuti instruksi pembayaran sesuai metode yang tersedia (transfer manual, Xendit, atau Midtrans).'],
            ['category_id' => $pembayaran, 'question' => 'Apakah saya bisa melihat riwayat pembayaran saya?', 'answer' => 'Bisa. Seluruh riwayat pembayaran dan bukti transaksi tersimpan dan dapat diunduh kapan saja melalui menu Riwayat Pembayaran di aplikasi.'],
            ['category_id' => $umum, 'question' => 'Apa itu fitur emergency / panic button?', 'answer' => 'Fitur ini mengirim sinyal darurat beserta lokasi Anda ke petugas keamanan dan admin kawasan hanya dengan sekali tekan. Fitur ini hanya tersedia setelah Anda login sebagai penghuni.'],
            ['category_id' => $umum, 'question' => 'Bagaimana cara mendaftarkan tamu sebelum berkunjung?', 'answer' => "Buka menu Pengelolaan Tamu pada aplikasi, isi data tamu Anda, dan tunjukkan kode konfirmasi kepada petugas keamanan di gerbang saat tamu tiba."],
            ['category_id' => $umum, 'question' => 'Apakah saya bisa booking fasilitas kawasan secara online?', 'answer' => 'Bisa. Login ke aplikasi, buka menu Booking Fasilitas, pilih fasilitas dan jadwal yang tersedia, lalu konfirmasi pemesanan Anda.'],
            ['category_id' => $umum, 'question' => 'Kemana saya harus melapor jika ada kerusakan fasilitas umum?', 'answer' => 'Sampaikan laporan melalui menu Pengaduan & Keluhan pada aplikasi. Petugas terkait akan menindaklanjuti dan Anda dapat memantau status penanganannya secara real-time.'],
        ];

        foreach ($faqs as $index => $faq) {
            LandingFaq::query()->firstOrCreate(['question' => $faq['question']], [
                'answer' => $faq['answer'],
                'category_id' => $faq['category_id'],
                'order' => $index,
                'is_active' => true,
            ]);
        }
    }

    private function seedEvents(): void
    {
        $events = [
            [
                'title' => 'Senam Pagi Bersama Warga',
                'slug' => 'senam-pagi-bersama-warga',
                'description' => 'Ajakan senam pagi bersama seluruh warga kawasan untuk menjaga kebugaran dan mempererat silaturahmi antarpenghuni. Terbuka untuk seluruh anggota keluarga.',
                'location' => 'Lapangan Serbaguna Kawasan',
                'starts_at' => now()->addDays(10)->setTime(6, 30),
                'ends_at' => now()->addDays(10)->setTime(8, 0),
                'status' => 'published',
                'img' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=700&q=60',
            ],
            [
                'title' => 'Bazar UMKM Warga Grand Duta',
                'slug' => 'bazar-umkm-warga-grand-duta',
                'description' => 'Bazar akhir pekan yang menampilkan produk UMKM milik warga kawasan, mulai dari kuliner hingga kerajinan tangan. Yuk dukung usaha sesama penghuni!',
                'location' => 'Area Komersial Kawasan',
                'starts_at' => now()->addDays(18)->setTime(9, 0),
                'ends_at' => now()->addDays(19)->setTime(17, 0),
                'status' => 'published',
                'img' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=700&q=60',
            ],
            [
                'title' => 'Rapat Warga Triwulan',
                'slug' => 'rapat-warga-triwulan',
                'description' => 'Rapat rutin triwulan membahas evaluasi pengelolaan kawasan, laporan keuangan, dan rencana kegiatan mendatang. Kehadiran perwakilan setiap unit sangat diharapkan.',
                'location' => 'Clubhouse',
                'starts_at' => now()->addDays(25)->setTime(19, 0),
                'ends_at' => now()->addDays(25)->setTime(21, 0),
                'status' => 'published',
                'img' => 'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?auto=format&fit=crop&w=700&q=60',
            ],
        ];

        foreach ($events as $index => $event) {
            LandingEvent::query()->firstOrCreate(['slug' => $event['slug']], [
                'title' => $event['title'],
                'banner_media_id' => $this->image($event['img'], $event['title']),
                'description' => $event['description'],
                'location' => $event['location'],
                'starts_at' => $event['starts_at'],
                'ends_at' => $event['ends_at'],
                'status' => $event['status'],
                'order' => $index,
                'is_active' => true,
            ]);
        }
    }

    private function seedArticles(): void
    {
        $articles = [
            [
                'title' => 'Jadwal Perawatan Rutin Kolam Renang Bulan Agustus',
                'slug' => 'jadwal-perawatan-rutin-kolam-renang-bulan-agustus',
                'date' => '2026-08-02',
                'img' => 'https://images.unsplash.com/photo-1530549387789-4c1017266635?auto=format&fit=crop&w=700&q=60',
                'excerpt' => 'Pengelola akan melaksanakan perawatan rutin kolam renang kawasan pada tanggal 2-3 Agustus 2026.',
                'content' => 'Pengelola akan melaksanakan perawatan rutin kolam renang kawasan pada tanggal 2-3 Agustus 2026 mulai pukul 08.00 hingga 16.00 WIB. Selama masa perawatan, fasilitas kolam renang tidak dapat digunakan oleh penghuni. Mohon maaf atas ketidaknyamanannya dan terima kasih atas pengertiannya.',
            ],
            [
                'title' => 'Gotong Royong Kebersihan Kawasan',
                'slug' => 'gotong-royong-kebersihan-kawasan',
                'date' => '2026-07-20',
                'img' => 'https://images.unsplash.com/photo-1618477388954-7852f32655ec?auto=format&fit=crop&w=700&q=60',
                'excerpt' => 'Ajakan gotong royong bersama seluruh penghuni untuk menjaga kebersihan dan kenyamanan kawasan.',
                'content' => 'Pengelola mengajak seluruh penghuni untuk berpartisipasi dalam kegiatan gotong royong kebersihan kawasan yang akan dilaksanakan setiap Minggu pagi. Kegiatan ini bertujuan untuk menjaga kebersihan lingkungan serta mempererat silaturahmi antarpenghuni.',
            ],
            [
                'title' => 'Peluncuran Fitur Pengaduan Digital',
                'slug' => 'peluncuran-fitur-pengaduan-digital',
                'date' => '2026-07-10',
                'img' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=700&q=60',
                'excerpt' => 'Kini penghuni dapat menyampaikan dan memantau status pengaduan langsung melalui aplikasi.',
                'content' => 'Sebagai bagian dari peningkatan layanan, pengelola resmi meluncurkan fitur pengaduan digital pada aplikasi Estate Management. Penghuni dapat menyampaikan keluhan seputar kawasan dan memantau status penanganannya secara real-time tanpa perlu datang ke kantor pengelola.',
            ],
        ];

        foreach ($articles as $index => $article) {
            LandingArticle::query()->firstOrCreate(['slug' => $article['slug']], [
                'title' => $article['title'],
                'excerpt' => $article['excerpt'],
                'content' => $article['content'],
                'featured_image_media_id' => $this->image($article['img'], $article['title']),
                'status' => 'published',
                'published_at' => $article['date'],
                'order' => $index,
                'is_active' => true,
            ]);
        }
    }

    private function seedGallery(array $categories): void
    {
        $kawasan = $categories['gallery:kawasan']->id;

        $album = LandingGalleryAlbum::query()->firstOrCreate(['slug' => 'fasilitas-kawasan'], [
            'title' => 'Fasilitas Kawasan',
            'category_id' => $kawasan,
            'cover_media_id' => $this->image('https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?auto=format&fit=crop&w=700&q=60', 'Fasilitas Kawasan'),
            'order' => 0,
            'is_active' => true,
        ]);

        $items = [
            ['caption' => 'Clubhouse', 'img' => 'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?auto=format&fit=crop&w=700&q=60'],
            ['caption' => 'Kolam Renang', 'img' => 'https://images.unsplash.com/photo-1519046904884-53103b34b206?auto=format&fit=crop&w=700&q=60'],
            ['caption' => 'Taman', 'img' => 'https://images.unsplash.com/photo-1585320806297-9794b3e4eeae?auto=format&fit=crop&w=700&q=60'],
            ['caption' => 'Lapangan Olahraga', 'img' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=700&q=60'],
        ];

        foreach ($items as $index => $item) {
            $album->items()->firstOrCreate(['caption' => $item['caption']], [
                'type' => 'image',
                'media_id' => $this->image($item['img'], $item['caption']),
                'order' => $index,
                'is_active' => true,
            ]);
        }

        $secondAlbum = LandingGalleryAlbum::query()->firstOrCreate(['slug' => 'kegiatan-warga'], [
            'title' => 'Kegiatan Warga',
            'category_id' => $kawasan,
            'cover_media_id' => $this->image('https://images.unsplash.com/photo-1618477388954-7852f32655ec?auto=format&fit=crop&w=700&q=60', 'Kegiatan Warga'),
            'order' => 1,
            'is_active' => true,
        ]);

        $secondItems = [
            ['caption' => 'Gotong Royong Kebersihan', 'img' => 'https://images.unsplash.com/photo-1618477388954-7852f32655ec?auto=format&fit=crop&w=700&q=60'],
            ['caption' => 'Senam Pagi Warga', 'img' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=700&q=60'],
        ];

        foreach ($secondItems as $index => $item) {
            $secondAlbum->items()->firstOrCreate(['caption' => $item['caption']], [
                'type' => 'image',
                'media_id' => $this->image($item['img'], $item['caption']),
                'order' => $index,
                'is_active' => true,
            ]);
        }
    }
}
