<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            // Nullable so existing units remain valid without a VA number
            // (backfilled gradually by admins); unique so it can safely
            // identify exactly one unit once assigned.
            $table->string('va_number', 32)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique(['va_number']);
            $table->dropColumn('va_number');
        });
    }
};
