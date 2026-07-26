<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Cluster;
use App\Models\ClusterMap;
use App\Models\ClusterMapComponentType;
use App\Models\ClusterMapObject;
use App\Models\ClusterMapVersion;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\CollectorAssignmentService;
use App\Services\PenaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClusterMapController extends Controller
{
    use ApiResponse;

    private const SHAPE_TYPES = ['rect', 'circle', 'triangle', 'trapezoid', 'polygon', 'line', 'text'];
    private const MAX_VERSIONS_KEPT = 30;

    public function show(Request $request, Cluster $cluster, PenaltyService $penaltyService, CollectorAssignmentService $assignmentService)
    {
        if ($request->user()->hasRole('collector')) {
            $clusterIds = Unit::query()->whereIn('id', $assignmentService->unitIdsFor($request->user()))->pluck('cluster_id')->unique();
            abort_unless($clusterIds->contains($cluster->id), 403, 'Peta cluster ini tidak ditugaskan kepada Anda.');
        }

        $map = $cluster->map()->first();

        $placedUnitIds = $map
            ? ClusterMapObject::query()->where('cluster_map_id', $map->id)->whereNotNull('unit_id')->pluck('unit_id')
            : collect();

        $availableUnits = $cluster->units()
            ->whereNotIn('id', $placedUnitIds)
            ->orderBy('block')->orderBy('lot_number')
            ->get(['id', 'block', 'lot_number', 'land_area', 'building_area']);

        return $this->success([
            'map' => $map ? $this->serializeMap($map, $penaltyService) : null,
            'cluster' => ['id' => $cluster->id, 'name' => $cluster->name],
            'component_types' => ClusterMapComponentType::query()->active()->orderBy('category')->orderBy('name')->get(),
            'available_units' => $availableUnits,
        ]);
    }

    public function store(Request $request, Cluster $cluster, AuditService $auditService)
    {
        $existing = $cluster->map()->first();
        if ($existing) {
            return $this->success(['id' => $existing->id], 'Peta cluster sudah tersedia.', 200);
        }

        $data = $request->validate([
            'canvas_type' => ['required', 'in:blank,image,geo'],
            'canvas_width' => ['sometimes', 'integer', 'min:200', 'max:10000'],
            'canvas_height' => ['sometimes', 'integer', 'min:200', 'max:10000'],
            'scale_meters_per_pixel' => ['nullable', 'numeric', 'min:0'],
            'latitude' => ['required_if:canvas_type,geo', 'numeric', 'between:-90,90'],
            'longitude' => ['required_if:canvas_type,geo', 'numeric', 'between:-180,180'],
            'zoom' => ['nullable', 'integer', 'min:3', 'max:19'],
        ]);

        $canvasWidth = $data['canvas_width'] ?? 1600;
        $canvasHeight = $data['canvas_height'] ?? 1000;
        $canvasType = $data['canvas_type'];
        $backgroundPath = null;
        $backgroundWidth = null;
        $backgroundHeight = null;

        if ($canvasType === 'geo') {
            $backgroundWidth = min($canvasWidth, 2000);
            $backgroundHeight = min($canvasHeight, 1400);
            $backgroundPath = $this->fetchGeoBackground(
                $data['latitude'], $data['longitude'], $data['zoom'] ?? 17,
                $backgroundWidth, $backgroundHeight
            );
            $canvasType = 'image';
        }

        $map = ClusterMap::query()->create([
            'cluster_id' => $cluster->id,
            'canvas_type' => $canvasType,
            'background_image_path' => $backgroundPath,
            'background_width' => $backgroundWidth,
            'background_height' => $backgroundHeight,
            'canvas_width' => $canvasWidth,
            'canvas_height' => $canvasHeight,
            'scale_meters_per_pixel' => $data['scale_meters_per_pixel'] ?? null,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $auditService->log('cluster_map_created', 'cluster-maps', 'CREATE', $map, [], $map->toArray());

        return $this->success(['id' => $map->id], 'Peta cluster berhasil dibuat.', 201);
    }

    public function uploadBackground(Request $request, ClusterMap $clusterMap, AuditService $auditService)
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'max:5120'],
            'background_width' => ['nullable', 'numeric', 'min:1'],
            'background_height' => ['nullable', 'numeric', 'min:1'],
        ]);

        $oldPath = $clusterMap->background_image_path;
        $stored = $data['image']->store('cluster-maps', 'public');

        $clusterMap->update([
            'canvas_type' => 'image',
            'background_image_path' => $stored,
            'background_width' => $data['background_width'] ?? $clusterMap->background_width,
            'background_height' => $data['background_height'] ?? $clusterMap->background_height,
            'updated_by' => $request->user()->id,
        ]);

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        $auditService->log('cluster_map_background_uploaded', 'cluster-maps', 'UPDATE', $clusterMap, [], ['background_image_path' => $stored]);

        return $this->success($clusterMap->refresh(), 'Gambar latar peta berhasil diunggah.');
    }

    public function updateSettings(Request $request, ClusterMap $clusterMap, AuditService $auditService)
    {
        $data = $request->validate([
            'canvas_width' => ['sometimes', 'integer', 'min:200', 'max:10000'],
            'canvas_height' => ['sometimes', 'integer', 'min:200', 'max:10000'],
            'grid_enabled' => ['sometimes', 'boolean'],
            'grid_size' => ['sometimes', 'integer', 'min:5', 'max:500'],
            'grid_visible' => ['sometimes', 'boolean'],
            'snap_enabled' => ['sometimes', 'boolean'],
            'scale_meters_per_pixel' => ['nullable', 'numeric', 'min:0'],
            'background_x' => ['sometimes', 'numeric'],
            'background_y' => ['sometimes', 'numeric'],
            'background_width' => ['nullable', 'numeric', 'min:1'],
            'background_height' => ['nullable', 'numeric', 'min:1'],
            'background_scale' => ['sometimes', 'numeric', 'min:0.05', 'max:20'],
            'background_opacity' => ['sometimes', 'numeric', 'min:0', 'max:1'],
        ]);

        $old = $clusterMap->toArray();
        $clusterMap->update([...$data, 'updated_by' => $request->user()->id]);
        $auditService->log('cluster_map_settings_updated', 'cluster-maps', 'UPDATE', $clusterMap, $old, $clusterMap->toArray());

        return $this->success($clusterMap->refresh(), 'Pengaturan peta berhasil disimpan.');
    }

    public function saveObjects(Request $request, ClusterMap $clusterMap, AuditService $auditService, PenaltyService $penaltyService)
    {
        $data = $request->validate([
            'objects' => ['present', 'array'],
            'objects.*.id' => ['required', 'string'],
            'objects.*.object_category' => ['required', Rule::in(['unit', 'component', 'label'])],
            'objects.*.shape_type' => ['required', Rule::in(self::SHAPE_TYPES)],
            'objects.*.unit_id' => ['nullable', 'string', 'exists:units,id'],
            'objects.*.component_type_id' => ['nullable', 'integer', 'exists:cluster_map_component_types,id'],
            'objects.*.x' => ['required', 'numeric'],
            'objects.*.y' => ['required', 'numeric'],
            'objects.*.width' => ['nullable', 'numeric', 'min:0'],
            'objects.*.height' => ['nullable', 'numeric', 'min:0'],
            'objects.*.rotation' => ['nullable', 'numeric'],
            'objects.*.points' => ['nullable', 'array'],
            'objects.*.fill_color' => ['nullable', 'string', 'max:20'],
            'objects.*.stroke_color' => ['nullable', 'string', 'max:20'],
            'objects.*.stroke_width' => ['nullable', 'numeric', 'min:0'],
            'objects.*.opacity' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'objects.*.layer_order' => ['nullable', 'integer'],
            'objects.*.is_locked' => ['nullable', 'boolean'],
            'objects.*.is_color_auto' => ['nullable', 'boolean'],
            'objects.*.label_text' => ['nullable', 'string'],
            'objects.*.group_id' => ['nullable', 'string', 'max:40'],
            'objects.*.metadata' => ['nullable', 'array'],
            'version_label' => ['nullable', 'string', 'max:100'],
        ]);

        $objects = $data['objects'];
        $this->assertObjectsValid($objects, $clusterMap);

        DB::transaction(function () use ($objects, $clusterMap, $request) {
            $incomingIds = collect($objects)->pluck('id');
            ClusterMapObject::query()->where('cluster_map_id', $clusterMap->id)
                ->whereNotIn('id', $incomingIds)->delete();

            foreach ($objects as $object) {
                ClusterMapObject::query()->updateOrCreate(
                    ['id' => $object['id']],
                    [
                        'cluster_map_id' => $clusterMap->id,
                        'object_category' => $object['object_category'],
                        'unit_id' => $object['unit_id'] ?? null,
                        'component_type_id' => $object['component_type_id'] ?? null,
                        'shape_type' => $object['shape_type'],
                        'x' => $object['x'],
                        'y' => $object['y'],
                        'width' => $object['width'] ?? null,
                        'height' => $object['height'] ?? null,
                        'rotation' => $object['rotation'] ?? 0,
                        'points' => $object['points'] ?? null,
                        'fill_color' => $object['fill_color'] ?? null,
                        'stroke_color' => $object['stroke_color'] ?? null,
                        'stroke_width' => $object['stroke_width'] ?? 1,
                        'opacity' => $object['opacity'] ?? 1,
                        'layer_order' => $object['layer_order'] ?? 0,
                        'is_locked' => $object['is_locked'] ?? false,
                        'is_color_auto' => $object['is_color_auto'] ?? true,
                        'label_text' => $object['label_text'] ?? null,
                        'group_id' => $object['group_id'] ?? null,
                        'metadata' => $object['metadata'] ?? null,
                        'created_by' => $request->user()->id,
                        'updated_by' => $request->user()->id,
                    ]
                );
            }

            $this->recordVersion($clusterMap, $request->user()->id, $request->input('version_label'));
        });

        $auditService->log('cluster_map_saved', 'cluster-maps', 'UPDATE', $clusterMap, [], ['object_count' => count($objects)]);

        return $this->success($this->serializeMap($clusterMap->fresh(), $penaltyService), 'Peta cluster berhasil disimpan.');
    }

    public function versions(ClusterMap $clusterMap)
    {
        $versions = $clusterMap->versions()->with('creator:id,name')->get(['id', 'cluster_map_id', 'label', 'created_by', 'created_at']);

        return $this->success($versions);
    }

    public function restoreVersion(Request $request, ClusterMapVersion $clusterMapVersion, AuditService $auditService, PenaltyService $penaltyService)
    {
        $map = $clusterMapVersion->clusterMap;
        $snapshot = $clusterMapVersion->snapshot;

        DB::transaction(function () use ($map, $snapshot, $request, $clusterMapVersion) {
            $map->update(array_merge(
                collect($snapshot['map'] ?? [])->only([
                    'canvas_type', 'background_image_path', 'background_x', 'background_y',
                    'background_width', 'background_height', 'background_scale', 'background_opacity',
                    'canvas_width', 'canvas_height', 'grid_enabled', 'grid_size', 'grid_visible',
                    'snap_enabled', 'scale_meters_per_pixel',
                ])->all(),
                ['updated_by' => $request->user()->id]
            ));

            ClusterMapObject::query()->where('cluster_map_id', $map->id)->delete();
            foreach ($snapshot['objects'] ?? [] as $object) {
                ClusterMapObject::query()->create(array_merge(
                    collect($object)->only([
                        'id', 'object_category', 'unit_id', 'component_type_id', 'shape_type',
                        'x', 'y', 'width', 'height', 'rotation', 'points', 'fill_color', 'stroke_color',
                        'stroke_width', 'opacity', 'layer_order', 'is_locked', 'is_color_auto',
                        'label_text', 'group_id', 'metadata',
                    ])->all(),
                    ['cluster_map_id' => $map->id, 'updated_by' => $request->user()->id]
                ));
            }

            $this->recordVersion($map, $request->user()->id, "Dipulihkan dari versi #{$clusterMapVersion->id}");
        });

        $auditService->log('cluster_map_version_restored', 'cluster-maps', 'UPDATE', $map, [], ['version_id' => $clusterMapVersion->id]);

        return $this->success($this->serializeMap($map->fresh(), $penaltyService), 'Versi peta berhasil dipulihkan.');
    }

    /**
     * Compose a background image from OpenStreetMap tiles centered at the given
     * coordinates, so a real-world location can be used as the map canvas background
     * the same way an uploaded floor plan image is used.
     */
    private function fetchGeoBackground(float $lat, float $lon, int $zoom, int $width, int $height): string
    {
        $tileSize = 256;
        $tilesPerSide = 2 ** $zoom;
        $worldSize = $tileSize * $tilesPerSide;

        $centerX = ($lon + 180) / 360 * $worldSize;
        $sinLat = sin(deg2rad($lat));
        $centerY = (0.5 - log((1 + $sinLat) / (1 - $sinLat)) / (4 * M_PI)) * $worldSize;

        $originX = $centerX - $width / 2;
        $originY = $centerY - $height / 2;

        $firstTileX = (int) floor($originX / $tileSize);
        $firstTileY = (int) floor($originY / $tileSize);
        $lastTileX = (int) floor(($originX + $width - 1) / $tileSize);
        $lastTileY = (int) floor(($originY + $height - 1) / $tileSize);

        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 240, 240, 240));

        $successCount = 0;
        for ($tx = $firstTileX; $tx <= $lastTileX; $tx++) {
            $wrappedX = (($tx % $tilesPerSide) + $tilesPerSide) % $tilesPerSide;
            for ($ty = $firstTileY; $ty <= $lastTileY; $ty++) {
                if ($ty < 0 || $ty >= $tilesPerSide) {
                    continue;
                }

                $response = Http::withHeaders(['User-Agent' => 'DutaResidenceEstate/1.0 (cluster-map background)'])
                    ->timeout(10)
                    ->get("https://tile.openstreetmap.org/{$zoom}/{$wrappedX}/{$ty}.png");

                if (! $response->successful()) {
                    continue;
                }

                $tileImage = @imagecreatefromstring($response->body());
                if (! $tileImage) {
                    continue;
                }

                $destX = (int) round($tx * $tileSize - $originX);
                $destY = (int) round($ty * $tileSize - $originY);
                imagecopy($canvas, $tileImage, $destX, $destY, 0, 0, $tileSize, $tileSize);
                imagedestroy($tileImage);
                $successCount++;
            }
        }

        if ($successCount === 0) {
            imagedestroy($canvas);
            throw ValidationException::withMessages([
                'latitude' => 'Gagal mengambil citra peta lokasi. Periksa koneksi internet server dan coba lagi.',
            ]);
        }

        ob_start();
        imagepng($canvas);
        $contents = ob_get_clean();
        imagedestroy($canvas);

        $path = 'cluster-maps/' . Str::random(40) . '.png';
        Storage::disk('public')->put($path, $contents);

        return $path;
    }

    private function assertObjectsValid(array $objects, ClusterMap $clusterMap): void
    {
        $errors = [];
        $seenUnitIds = [];

        foreach ($objects as $index => $object) {
            if (in_array($object['shape_type'], ['polygon', 'line'], true) && empty($object['points'])) {
                $errors["objects.$index.points"] = 'Titik poligon/garis wajib diisi untuk bentuk ini.';
            }

            if ($object['object_category'] === 'unit') {
                $unitId = $object['unit_id'] ?? null;
                if (! $unitId) {
                    $errors["objects.$index.unit_id"] = 'Objek unit wajib memiliki unit_id.';
                    continue;
                }

                if (isset($seenUnitIds[$unitId])) {
                    $errors["objects.$index.unit_id"] = "Unit {$unitId} ditempatkan lebih dari sekali pada peta ini.";
                }
                $seenUnitIds[$unitId] = true;

                $belongsToCluster = DB::table('units')->where('id', $unitId)->where('cluster_id', $clusterMap->cluster_id)->exists();
                if (! $belongsToCluster) {
                    $errors["objects.$index.unit_id"] = "Unit {$unitId} bukan bagian dari cluster ini.";
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function recordVersion(ClusterMap $map, int $userId, ?string $label): void
    {
        $snapshot = [
            'map' => $map->fresh()->toArray(),
            'objects' => $map->objects()->get()->toArray(),
        ];

        ClusterMapVersion::query()->create([
            'cluster_map_id' => $map->id,
            'label' => $label,
            'snapshot' => $snapshot,
            'created_by' => $userId,
            'created_at' => now(),
        ]);

        $staleIds = $map->versions()->orderByDesc('created_at')->pluck('id')->slice(self::MAX_VERSIONS_KEPT);
        if ($staleIds->isNotEmpty()) {
            ClusterMapVersion::query()->whereIn('id', $staleIds)->delete();
        }
    }

    private function serializeMap(ClusterMap $map, PenaltyService $penaltyService): array
    {
        $objects = $map->objects()->with(['unit.resident:id,name,phone', 'unit.occupancy', 'unit.status', 'componentType'])->get();

        $objects = $objects->map(function (ClusterMapObject $object) use ($penaltyService) {
            $payload = $object->toArray();

            if ($object->object_category === ClusterMapObject::CATEGORY_UNIT && $object->unit) {
                $unit = $object->unit;
                $outstanding = $penaltyService->calculateUnitOutstanding($unit->id);

                $payload['unit_detail'] = [
                    'id' => $unit->id,
                    'block' => $unit->block,
                    'lot_number' => $unit->lot_number,
                    'land_area' => $unit->land_area,
                    'building_area' => $unit->building_area,
                    'resident_name' => $unit->resident?->name,
                    'resident_phone' => $unit->resident?->phone,
                    'occupancy_id' => $unit->occupancy_id,
                    'occupancy_name' => $unit->occupancy?->name,
                    'status_id' => $unit->status_id,
                    'status_name' => $unit->status?->name,
                    'has_arrears' => $outstanding['total_outstanding'] > 0,
                    'total_outstanding' => $outstanding['total_outstanding'],
                ];
            }

            return $payload;
        });

        return [
            ...$map->toArray(),
            'objects' => $objects,
        ];
    }
}
