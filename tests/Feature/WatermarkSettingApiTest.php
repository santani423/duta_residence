<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WatermarkSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WatermarkSettingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_authenticated_role_can_read_watermark_settings(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'loket')->first());
        $this->getJson('/api/v1/watermark-settings')
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.mode', 'single');
    }

    public function test_only_super_admin_can_update_watermark_settings(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'loket')->first());
        $this->putJson('/api/v1/admin/watermark-settings', [
            'enabled' => true,
            'type' => 'text',
            'text_content' => 'Rahasia',
            'opacity' => 40,
            'mode' => 'single',
            'size' => 200,
            'position' => 'center',
        ])->assertForbidden();

        Sanctum::actingAs(User::where('username', 'admin.estate')->first());
        $this->putJson('/api/v1/admin/watermark-settings', [
            'enabled' => true,
            'type' => 'text',
            'text_content' => 'Rahasia',
            'opacity' => 40,
            'mode' => 'single',
            'size' => 200,
            'position' => 'center',
        ])->assertForbidden();
    }

    public function test_super_admin_can_update_watermark_settings(): void
    {
        $this->seed();

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        Sanctum::actingAs($superAdmin);
        $response = $this->putJson('/api/v1/admin/watermark-settings', [
            'enabled' => true,
            'type' => 'text',
            'text_content' => 'Duta Indah',
            'opacity' => 45,
            'mode' => 'multiple',
            'size' => 220,
            'spacing' => 120,
        ])->assertOk();

        $response->assertJsonPath('data.enabled', true);
        $response->assertJsonPath('data.mode', 'multiple');
        $this->assertSame(1, WatermarkSetting::count());

        $public = $this->getJson('/api/v1/watermark-settings')->assertOk();
        $public->assertJsonPath('data.text_content', 'Duta Indah');
    }

    public function test_watermark_update_validates_mode_specific_fields(): void
    {
        $this->seed();

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        Sanctum::actingAs($superAdmin);
        $this->putJson('/api/v1/admin/watermark-settings', [
            'enabled' => true,
            'type' => 'text',
            'text_content' => 'Duta Indah',
            'opacity' => 45,
            'mode' => 'multiple',
            'size' => 220,
            // spacing missing while mode = multiple
        ])->assertStatus(422)->assertJsonValidationErrors(['spacing']);
    }
}
