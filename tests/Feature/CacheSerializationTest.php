<?php

namespace Tests\Feature;

use App\Models\HelpSetting;
use App\Models\User;
use App\Support\LandingContentCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * phpunit.xml memakai cache store `array` tanpa serialisasi, jadi nilai yang di-cache tidak pernah
 * melewati unserialize(). Test di sini memakai store `database` agar cache hit benar-benar dibaca ulang
 * dari storage seperti store file/database/redis di production, termasuk batasan
 * `cache.serializable_classes` (Laravel 13).
 */
class CacheSerializationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'database']);
    }

    public function test_landing_content_is_identical_on_cache_miss_and_hit(): void
    {
        $this->seed();

        $miss = $this->getJson('/api/v1/landing/content')->assertOk()->json();
        $this->assertDatabaseHas('cache', ['key' => config('cache.prefix').LandingContentCache::KEY]);
        $hit = $this->getJson('/api/v1/landing/content')->assertOk()->json();

        $this->assertNotEmpty($miss['data']['hero_slides']);
        $this->assertNotEmpty($miss['data']['statistics']);
        $this->assertArrayHasKey('resolved_value', $miss['data']['statistics'][0]);
        $this->assertSame($miss, $hit);
    }

    public function test_help_settings_resolve_identically_on_cache_miss_and_hit(): void
    {
        $this->seed();
        HelpSetting::query()->updateOrCreate(['scope_type' => 'global', 'scope_key' => null], ['is_enabled' => false]);
        HelpSetting::query()->updateOrCreate(['scope_type' => 'role', 'scope_key' => 'cs'], ['is_enabled' => true]);
        HelpSetting::forgetCache();

        $this->assertFalse(HelpSetting::isEnabled([]));
        $this->assertDatabaseHas('cache', ['key' => config('cache.prefix').HelpSetting::CACHE_KEY]);
        $this->assertFalse(HelpSetting::isEnabled([]));
        $this->assertTrue(HelpSetting::isEnabled(['role' => 'cs']));
        $this->assertInstanceOf(HelpSetting::class, HelpSetting::allCached()->first());

        Sanctum::actingAs(User::where('username', 'customer.01002')->first());
        HelpSetting::forgetCache();
        $miss = $this->getJson('/api/v1/help-settings')->assertOk()->json('data.settings');
        $hit = $this->getJson('/api/v1/help-settings')->assertOk()->json('data.settings');

        $this->assertNotEmpty($miss);
        $this->assertSame($miss, $hit);
    }

    public function test_permission_checks_work_when_permissions_are_read_back_from_cache(): void
    {
        $this->seed();
        $registrar = app(PermissionRegistrar::class);
        $registrar->initializeCache();
        $registrar->forgetCachedPermissions();

        Sanctum::actingAs(User::where('username', 'admin.estate')->first());
        $this->getJson('/api/v1/clusters')->assertOk();
        $this->assertDatabaseHas('cache', ['key' => config('cache.prefix').config('permission.cache.key')]);

        $registrar->clearPermissionsCollection();
        $this->getJson('/api/v1/clusters')->assertOk();

        Sanctum::actingAs(User::where('username', 'customer.01002')->first());
        $registrar->clearPermissionsCollection();
        $this->getJson('/api/v1/clusters')->assertForbidden();
    }
}
