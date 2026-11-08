<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // LandingCmsSeeder downloads stock photos from Unsplash on every $this->seed().
        // Serve a tiny generated JPEG instead so seeding is fast and works offline.
        Http::fake(['images.unsplash.com/*' => Http::response(self::tinyJpeg(), 200, ['Content-Type' => 'image/jpeg'])]);
    }

    /**
     * Token API untuk akun lewat jalur login, sehingga token ini menjadi sesi aktif akun
     * (token yang dibuat langsung dengan createToken() bukan sesi aktif dan ditolak).
     */
    protected function apiTokenFor(User $user, array $abilities = ['*']): string
    {
        return $user->startSingleSession($abilities, now()->addHours(8))->plainTextToken;
    }

    private static function tinyJpeg(): string
    {
        $image = imagecreatetruecolor(8, 8);
        ob_start();
        imagejpeg($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
