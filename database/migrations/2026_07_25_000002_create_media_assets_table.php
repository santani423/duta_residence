<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 20)->default('public');
            $table->string('path', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->json('variants')->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            // Informational only: records where this asset was first uploaded from, for
            // media-library filtering UI. Never used for cascade or authorization logic -
            // a MediaAsset can legitimately be reused by many unrelated CMS records via
            // their own *_media_id foreign keys.
            $table->string('entity_type', 100)->nullable();
            $table->string('entity_id', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
