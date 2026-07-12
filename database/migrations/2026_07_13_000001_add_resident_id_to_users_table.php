<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('resident_id', 8)->nullable()->after('unit_id');
            $table->foreign('resident_id')->references('id')->on('residents')->nullOnDelete();
            $table->index('resident_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['resident_id']);
            $table->dropIndex(['resident_id']);
            $table->dropColumn('resident_id');
        });
    }
};
