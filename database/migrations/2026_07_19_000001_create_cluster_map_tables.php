<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cluster_map_component_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->string('category', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('fill_color', 20)->default('#91caff');
            $table->string('stroke_color', 20)->default('#1677ff');
            $table->string('default_shape_type', 20)->default('rect');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cluster_maps', function (Blueprint $table) {
            $table->id();
            $table->string('cluster_id', 2)->unique();
            $table->foreign('cluster_id')->references('id')->on('clusters')->cascadeOnDelete();
            $table->string('canvas_type', 10)->default('blank'); // 'blank' | 'image'
            $table->string('background_image_path')->nullable();
            $table->float('background_x')->default(0);
            $table->float('background_y')->default(0);
            $table->float('background_width')->nullable();
            $table->float('background_height')->nullable();
            $table->float('background_scale')->default(1);
            $table->float('background_opacity')->default(1);
            $table->unsignedInteger('canvas_width')->default(1600);
            $table->unsignedInteger('canvas_height')->default(1000);
            $table->boolean('grid_enabled')->default(true);
            $table->unsignedInteger('grid_size')->default(20);
            $table->boolean('grid_visible')->default(true);
            $table->boolean('snap_enabled')->default(true);
            $table->float('scale_meters_per_pixel')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cluster_map_objects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('cluster_map_id')->constrained('cluster_maps')->cascadeOnDelete();
            $table->string('object_category', 20); // 'unit' | 'component' | 'label'
            $table->string('unit_id', 5)->nullable();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->foreignId('component_type_id')->nullable()
                ->constrained('cluster_map_component_types')->nullOnDelete();
            $table->string('shape_type', 20); // rect|circle|triangle|trapezoid|polygon|line|text
            $table->float('x')->default(0);
            $table->float('y')->default(0);
            $table->float('width')->nullable();
            $table->float('height')->nullable();
            $table->float('rotation')->default(0);
            $table->json('points')->nullable();
            $table->string('fill_color', 20)->nullable();
            $table->string('stroke_color', 20)->nullable();
            $table->float('stroke_width')->default(1);
            $table->float('opacity')->default(1);
            $table->integer('layer_order')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_color_auto')->default(true);
            $table->string('label_text')->nullable();
            $table->string('group_id', 40)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cluster_map_id', 'unit_id']);
            $table->index(['cluster_map_id', 'object_category']);
        });

        Schema::create('cluster_map_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cluster_map_id')->constrained('cluster_maps')->cascadeOnDelete();
            $table->string('label', 100)->nullable();
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_map_versions');
        Schema::dropIfExists('cluster_map_objects');
        Schema::dropIfExists('cluster_maps');
        Schema::dropIfExists('cluster_map_component_types');
    }
};
