<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('subtitle', 255)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('background_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('video_url', 500)->nullable();
            $table->string('cta_text', 60)->nullable();
            $table->string('cta_url', 255)->nullable();
            $table->string('cta_target', 10)->default('_self');
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'order']);
        });

        Schema::create('landing_services', function (Blueprint $table) {
            $table->id();
            $table->string('icon', 60)->nullable();
            $table->foreignId('image_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('landing_content_categories')->nullOnDelete();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'order']);
        });

        Schema::create('landing_features', function (Blueprint $table) {
            $table->id();
            $table->string('icon', 60)->nullable();
            $table->foreignId('image_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('badge', 60)->nullable();
            $table->string('cta_text', 60)->nullable();
            $table->string('cta_url', 255)->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'order']);
        });

        Schema::create('landing_statistics', function (Blueprint $table) {
            $table->id();
            $table->string('label', 150);
            $table->decimal('value', 14, 2)->default(0);
            $table->string('suffix', 20)->nullable();
            $table->string('icon', 60)->nullable();
            $table->string('source', 30)->default('manual'); // manual | residents_count | units_count | clusters_count
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'order']);
        });

        Schema::create('landing_testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('photo_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('position', 150)->nullable();
            $table->text('content');
            $table->unsignedTinyInteger('rating')->default(5);
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'order']);
        });

        Schema::create('landing_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('logo_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('website_url', 255)->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'order']);
        });

        Schema::create('landing_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question', 300);
            $table->text('answer');
            $table->foreignId('category_id')->nullable()->constrained('landing_content_categories')->nullOnDelete();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_faqs');
        Schema::dropIfExists('landing_partners');
        Schema::dropIfExists('landing_testimonials');
        Schema::dropIfExists('landing_statistics');
        Schema::dropIfExists('landing_features');
        Schema::dropIfExists('landing_services');
        Schema::dropIfExists('hero_slides');
    }
};
