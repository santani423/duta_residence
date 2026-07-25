<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_gallery_albums', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 220);
            $table->foreignId('cover_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('landing_content_categories')->nullOnDelete();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'order']);
        });

        Schema::create('landing_gallery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained('landing_gallery_albums')->cascadeOnDelete();
            $table->string('type', 10)->default('image'); // image | video
            $table->foreignId('media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('video_url', 500)->nullable();
            $table->string('caption', 255)->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['album_id', 'is_active', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_gallery_items');
        Schema::dropIfExists('landing_gallery_albums');
    }
};
