<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            // Deliberately not a unique() index: MySQL unique indexes don't exclude
            // soft-deleted rows, so uniqueness is enforced in the controller instead
            // (see LandingEventController) to avoid a permanently-blocked slug after delete.
            $table->string('slug', 220);
            $table->foreignId('banner_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('location', 255)->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('registration_url', 255)->nullable();
            $table->string('status', 20)->default('draft'); // draft | published
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index(['status', 'starts_at']);
        });

        Schema::create('landing_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('landing_content_categories')->nullOnDelete();
            $table->string('title', 250);
            $table->string('slug', 270);
            $table->string('excerpt', 500)->nullable();
            $table->longText('content')->nullable();
            $table->foreignId('featured_image_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('thumbnail_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('meta_keywords', 500)->nullable();
            $table->string('status', 20)->default('draft'); // draft | scheduled | published
            $table->timestamp('published_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_articles');
        Schema::dropIfExists('landing_events');
    }
};
