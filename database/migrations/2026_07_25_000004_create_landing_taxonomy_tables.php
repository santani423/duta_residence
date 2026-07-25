<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_content_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->string('group', 20); // service | news | gallery | faq
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['group', 'slug']);
            $table->index(['group', 'order']);
        });

        Schema::create('nav_menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('label', 100);
            $table->string('url', 255);
            $table->boolean('open_in_new_tab')->default(false);
            $table->boolean('show_in_footer')->default(true);
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'order']);
        });

        Schema::create('landing_social_links', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 30); // facebook | instagram | tiktok | youtube | linkedin | twitter_x | telegram | custom
            $table->string('url', 255);
            $table->string('icon', 60)->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_social_links');
        Schema::dropIfExists('nav_menu_items');
        Schema::dropIfExists('landing_content_categories');
    }
};
