<?php

namespace Tests\Feature;

use App\Models\LandingHeaderSetting;
use App\Models\LandingSeoSetting;
use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteIdentityFaviconTest extends TestCase
{
    use RefreshDatabase;

    public function test_favicon_is_null_until_the_cms_has_a_logo(): void
    {
        $this->getJson('/api/v1/site-identity')->assertOk()->assertJsonPath('data.favicon', null);
    }

    public function test_favicon_follows_header_logo_and_prefers_the_seo_favicon(): void
    {
        $logo = MediaAsset::create(['disk' => 'public', 'path' => 'cms/logo.png', 'mime_type' => 'image/png']);
        LandingHeaderSetting::current()->update(['logo_media_id' => $logo->id]);

        $this->getJson('/api/v1/site-identity')->assertOk()->assertJsonPath('data.favicon.path', 'cms/logo.png');

        $favicon = MediaAsset::create(['disk' => 'public', 'path' => 'cms/favicon.png', 'mime_type' => 'image/png']);
        LandingSeoSetting::current()->update(['favicon_media_id' => $favicon->id]);

        $this->getJson('/api/v1/site-identity')->assertOk()->assertJsonPath('data.favicon.path', 'cms/favicon.png');
    }
}
