<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_about_settings', function (Blueprint $table) {
            // Array of {icon, title, description} trust badges (Keamanan/Kenyamanan/
            // Transparansi, ...) shown alongside the About copy. Small, fixed-ish
            // cardinality content tightly coupled to this section, so it lives as a
            // JSON column on the singleton rather than its own collection table -
            // the same pattern already used for landing_contact_settings.business_hours.
            $table->json('pillars')->nullable()->after('image_media_id');
        });
    }

    public function down(): void
    {
        Schema::table('landing_about_settings', function (Blueprint $table) {
            $table->dropColumn('pillars');
        });
    }
};
