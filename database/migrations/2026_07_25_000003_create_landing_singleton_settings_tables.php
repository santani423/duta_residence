<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_header_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logo_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('site_name', 150)->default('Grand Duta');
            $table->boolean('sticky_enabled')->default(true);
            $table->boolean('show_login_button')->default(true);
            $table->string('login_button_text', 60)->default('Login');
            $table->string('cta_button_text', 60)->nullable();
            $table->string('cta_button_url', 255)->nullable();
            $table->boolean('cta_button_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('landing_about_settings', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200)->nullable();
            $table->string('subtitle', 200)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('image_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('landing_contact_settings', function (Blueprint $table) {
            $table->id();
            $table->string('address', 500)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('whatsapp', 60)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('maps_embed_url', 500)->nullable();
            $table->decimal('maps_lat', 10, 7)->nullable();
            $table->decimal('maps_lng', 10, 7)->nullable();
            $table->json('business_hours')->nullable();
            $table->timestamps();
        });

        Schema::create('landing_footer_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logo_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('copyright_text', 255)->nullable();
            $table->boolean('show_social_links')->default(true);
            $table->boolean('show_quick_links')->default(true);
            $table->timestamps();
        });

        Schema::create('landing_seo_settings', function (Blueprint $table) {
            $table->id();
            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('meta_keywords', 500)->nullable();
            $table->string('og_title', 255)->nullable();
            $table->string('og_description', 500)->nullable();
            $table->foreignId('og_image_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('twitter_card_type', 30)->default('summary_large_image');
            $table->foreignId('favicon_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->text('structured_data')->nullable();
            $table->timestamps();
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name', 150)->default('Grand Duta Estate Management');
            $table->foreignId('logo_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('default_theme', 10)->default('system'); // light | dark | system
            $table->string('primary_color', 20)->default('#0f766e');
            $table->string('secondary_color', 20)->default('#f59e0b');
            $table->string('default_language', 10)->default('id');
            $table->boolean('maintenance_mode')->default(false);
            $table->string('maintenance_message', 500)->nullable();
            $table->text('analytics_script')->nullable();
            $table->text('pixel_script')->nullable();
            $table->text('custom_css')->nullable();
            $table->text('custom_js')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('landing_seo_settings');
        Schema::dropIfExists('landing_footer_settings');
        Schema::dropIfExists('landing_contact_settings');
        Schema::dropIfExists('landing_about_settings');
        Schema::dropIfExists('landing_header_settings');
    }
};
