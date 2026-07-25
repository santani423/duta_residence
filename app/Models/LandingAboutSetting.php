<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingAboutSetting extends Model
{
    protected $fillable = ['title', 'subtitle', 'description', 'image_media_id', 'pillars'];

    protected $casts = [
        'pillars' => 'array',
    ];

    public function image()
    {
        return $this->belongsTo(MediaAsset::class, 'image_media_id');
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'title' => 'Pengelola Kawasan yang Mengutamakan Kenyamanan Anda',
            'pillars' => [
                ['icon' => 'SafetyCertificateOutlined', 'title' => 'Keamanan', 'description' => 'Pengawasan kawasan 24 jam, buku tamu digital, dan sinyal darurat yang terhubung langsung ke petugas.'],
                ['icon' => 'SmileOutlined', 'title' => 'Kenyamanan', 'description' => 'Fasilitas terawat dan proses layanan penghuni yang cepat, tanpa antrean panjang.'],
                ['icon' => 'EyeOutlined', 'title' => 'Transparansi', 'description' => 'Riwayat tagihan, pembayaran, dan pengaduan dapat dipantau kapan saja secara real-time.'],
            ],
        ]);
    }
}
