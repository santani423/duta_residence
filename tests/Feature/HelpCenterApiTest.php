<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HelpCenterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_book_content_is_filtered_by_role(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'admin.estate')->first());
        $adminSlugs = collect($this->getJson('/api/v1/manual-book/sections')->assertOk()->json('data'))
            ->pluck('slug')->all();
        $this->assertContains('kelola-user', $adminSlugs, 'admin_estate should see the users guide');
        $this->assertNotContains('panduan-penghuni', $adminSlugs, 'admin_estate should not see the customer-only guide');

        Sanctum::actingAs(User::where('username', 'customer.al001')->first());
        $customerSlugs = collect($this->getJson('/api/v1/manual-book/sections')->assertOk()->json('data'))
            ->pluck('slug')->all();
        $this->assertContains('panduan-penghuni', $customerSlugs);
        $this->assertContains('pengenalan-aplikasi', $customerSlugs, 'general guides are visible to every role');
        $this->assertNotContains('kelola-user', $customerSlugs, 'customer must not see internal admin guides');
        $this->assertNotContains('kelola-data-penghuni', $customerSlugs);
    }

    public function test_manual_book_section_management_requires_permission(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'loket')->first());
        $this->getJson('/api/v1/admin/manual-book/sections')->assertForbidden();
        $this->postJson('/api/v1/admin/manual-book/sections', [
            'module' => 'general', 'slug' => 'coba', 'title' => 'Coba',
        ])->assertForbidden();

        Sanctum::actingAs(User::where('username', 'admin.estate')->first());
        $response = $this->postJson('/api/v1/admin/manual-book/sections', [
            'module' => 'general',
            'slug' => 'panduan-baru',
            'title' => 'Panduan Baru',
            'summary' => 'Ringkasan',
            'roles' => ['customer'],
        ])->assertCreated();

        $sectionId = $response->json('data.id');

        // Section is scoped to 'customer' only, so admin_estate itself should no longer see it in the reader endpoint.
        $adminSlugs = collect($this->getJson('/api/v1/manual-book/sections')->json('data'))->pluck('slug')->all();
        $this->assertNotContains('panduan-baru', $adminSlugs);

        Sanctum::actingAs(User::where('username', 'customer.al001')->first());
        $customerSlugs = collect($this->getJson('/api/v1/manual-book/sections')->json('data'))->pluck('slug')->all();
        $this->assertContains('panduan-baru', $customerSlugs);

        $this->getJson("/api/v1/manual-book/sections/{$sectionId}")->assertOk();
        $this->postJson("/api/v1/manual-book/sections/{$sectionId}/read")->assertOk();
    }

    public function test_guided_tours_are_filtered_by_role_and_progress_can_be_saved(): void
    {
        $this->seed();

        $customer = User::where('username', 'customer.al001')->first();
        Sanctum::actingAs($customer);

        $tours = $this->getJson('/api/v1/guided-tours')->assertOk()->json('data');
        $this->assertNotEmpty($tours, 'the general navigation tour has no role restriction, so it must be visible');
        $tourId = $tours[0]['id'];

        $this->postJson("/api/v1/guided-tours/{$tourId}/progress", [
            'status' => 'completed',
            'last_step' => 4,
        ])->assertOk();

        $this->assertDatabaseHas('user_tour_progress', [
            'user_id' => $customer->id,
            'guided_tour_id' => $tourId,
            'status' => 'completed',
        ]);
    }

    public function test_help_settings_are_readable_by_any_authenticated_user_but_only_admin_can_change_them(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'customer.al001')->first());
        $this->getJson('/api/v1/help-settings')->assertOk();
        $this->putJson('/api/v1/admin/help-settings', [
            'settings' => [['scope_type' => 'global', 'is_enabled' => false]],
        ])->assertForbidden();

        Sanctum::actingAs(User::where('username', 'root')->first());
        $this->putJson('/api/v1/admin/help-settings', [
            'settings' => [
                ['scope_type' => 'global', 'is_enabled' => false],
                ['scope_type' => 'role', 'scope_key' => 'cs', 'is_enabled' => true],
            ],
        ])->assertOk();

        $this->assertFalse(\App\Models\HelpSetting::isEnabled([]));
        $this->assertTrue(\App\Models\HelpSetting::isEnabled(['role' => 'cs']));
    }
}
