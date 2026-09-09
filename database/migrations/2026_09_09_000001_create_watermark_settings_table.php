<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watermark_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('type', 10)->default('text');
            $table->string('text_content', 100)->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->unsignedTinyInteger('opacity')->default(30);
            $table->string('mode', 10)->default('single');
            $table->unsignedSmallInteger('size')->default(200);
            $table->string('position', 20)->default('bottom-right');
            $table->unsignedSmallInteger('spacing')->default(150);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watermark_settings');
    }
};
