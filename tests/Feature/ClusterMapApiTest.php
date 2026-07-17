<?php

namespace Tests\Feature;

use App\Models\ClusterMap;
use App\Models\ClusterMapComponentType;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClusterMapApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function createMapForGa(): ClusterMap
    {
        Sanctum::actingAs(User::where('username', 'root')->first());
        $this->postJson('/api/v1/clusters/GA/map', ['canvas_type' => 'blank'])->assertCreated();

        return ClusterMap::query()->where('cluster_id', 'GA')->firstOrFail();
    }

    public function test_creating_a_map_twice_for_the_same_cluster_returns_the_existing_one(): void
    {
        Sanctum::actingAs(User::where('username', 'root')->first());

        $firstId = $this->postJson('/api/v1/clusters/GA/map', ['canvas_type' => 'blank'])
            ->assertCreated()->json('data.id');

        $response = $this->postJson('/api/v1/clusters/GA/map', ['canvas_type' => 'blank'])->assertOk();

        $this->assertSame($firstId, $response->json('data.id'));
        $this->assertSame(1, ClusterMap::query()->where('cluster_id', 'GA')->count());
    }

    public function test_show_lists_available_units_and_seeded_component_types_before_any_object_is_placed(): void
    {
        $map = $this->createMapForGa();

        $response = $this->getJson('/api/v1/clusters/GA/map')->assertOk();

        $unitIds = collect($response->json('data.available_units'))->pluck('id')->all();
        $this->assertContains('GA001', $unitIds);
        $this->assertGreaterThan(0, count($response->json('data.component_types')));
        $this->assertSame($map->id, $response->json('data.map.id'));
    }

    public function test_saving_objects_persists_a_unit_and_a_component_and_writes_a_version_snapshot(): void
    {
        $map = $this->createMapForGa();
        $componentType = ClusterMapComponentType::query()->where('code', 'taman')->firstOrFail();

        $unitObjectId = (string) Str::uuid();
        $componentObjectId = (string) Str::uuid();

        $response = $this->putJson("/api/v1/cluster-maps/{$map->id}/objects", [
            'objects' => [
                [
                    'id' => $unitObjectId,
                    'object_category' => 'unit',
                    'unit_id' => 'GA001',
                    'shape_type' => 'rect',
                    'x' => 10, 'y' => 20, 'width' => 60, 'height' => 120,
                ],
                [
                    'id' => $componentObjectId,
                    'object_category' => 'component',
                    'component_type_id' => $componentType->id,
                    'shape_type' => 'polygon',
                    'x' => 200, 'y' => 200,
                    'points' => [0, 0, 50, 0, 50, 50, 0, 50],
                ],
            ],
        ])->assertOk();

        $this->assertCount(2, $response->json('data.objects'));
        $this->assertDatabaseHas('cluster_map_objects', ['id' => $unitObjectId, 'unit_id' => 'GA001']);
        $this->assertDatabaseHas('cluster_map_versions', ['cluster_map_id' => $map->id]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'cluster-maps', 'activity' => 'cluster_map_saved']);
    }

    public function test_placing_the_same_unit_twice_in_one_save_is_rejected(): void
    {
        $map = $this->createMapForGa();

        $this->putJson("/api/v1/cluster-maps/{$map->id}/objects", [
            'objects' => [
                ['id' => (string) Str::uuid(), 'object_category' => 'unit', 'unit_id' => 'GA001', 'shape_type' => 'rect', 'x' => 0, 'y' => 0],
                ['id' => (string) Str::uuid(), 'object_category' => 'unit', 'unit_id' => 'GA001', 'shape_type' => 'rect', 'x' => 10, 'y' => 10],
            ],
        ])->assertStatus(422);
    }

    public function test_placing_a_unit_from_a_different_cluster_is_rejected(): void
    {
        $map = $this->createMapForGa();
        $otherClusterUnit = Unit::query()->where('cluster_id', '!=', 'GA')->firstOrFail();

        $this->putJson("/api/v1/cluster-maps/{$map->id}/objects", [
            'objects' => [
                ['id' => (string) Str::uuid(), 'object_category' => 'unit', 'unit_id' => $otherClusterUnit->id, 'shape_type' => 'rect', 'x' => 0, 'y' => 0],
            ],
        ])->assertStatus(422);
    }

    public function test_user_without_edit_permission_cannot_save_objects_but_can_view_the_map(): void
    {
        $map = $this->createMapForGa();

        Sanctum::actingAs(User::where('username', 'loket')->first());

        $this->getJson('/api/v1/clusters/GA/map')->assertOk();
        $this->putJson("/api/v1/cluster-maps/{$map->id}/objects", ['objects' => []])->assertForbidden();
    }

    public function test_version_can_be_restored_after_a_later_save_overwrites_it(): void
    {
        $map = $this->createMapForGa();
        $firstObjectId = (string) Str::uuid();

        $this->putJson("/api/v1/cluster-maps/{$map->id}/objects", [
            'objects' => [
                ['id' => $firstObjectId, 'object_category' => 'unit', 'unit_id' => 'GA001', 'shape_type' => 'rect', 'x' => 0, 'y' => 0],
            ],
        ])->assertOk();

        $firstVersionId = $this->getJson("/api/v1/cluster-maps/{$map->id}/versions")
            ->assertOk()->json('data.0.id');

        $secondObjectId = (string) Str::uuid();
        $this->putJson("/api/v1/cluster-maps/{$map->id}/objects", [
            'objects' => [
                ['id' => $secondObjectId, 'object_category' => 'unit', 'unit_id' => 'GA002', 'shape_type' => 'rect', 'x' => 5, 'y' => 5],
            ],
        ])->assertOk();

        $this->assertDatabaseMissing('cluster_map_objects', ['id' => $firstObjectId]);

        $this->postJson("/api/v1/cluster-map-versions/{$firstVersionId}/restore")->assertOk();

        $this->assertDatabaseHas('cluster_map_objects', ['id' => $firstObjectId, 'unit_id' => 'GA001']);
        $this->assertDatabaseMissing('cluster_map_objects', ['id' => $secondObjectId]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'cluster-maps', 'activity' => 'cluster_map_version_restored']);
    }
}
