<?php

namespace Tests;

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

    private static function tinyJpeg(): string
    {
        $image = imagecreatetruecolor(8, 8);
        ob_start();
        imagejpeg($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
