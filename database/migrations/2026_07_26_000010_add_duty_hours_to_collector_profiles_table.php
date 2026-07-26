<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collector_profiles', function (Blueprint $table) {
            $table->time('duty_start_time')->nullable()->after('working_area_notes');
            $table->time('duty_end_time')->nullable()->after('duty_start_time');
        });
    }

    public function down(): void
    {
        Schema::table('collector_profiles', function (Blueprint $table) {
            $table->dropColumn(['duty_start_time', 'duty_end_time']);
        });
    }
};
