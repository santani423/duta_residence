<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supervisor_notifications', function (Blueprint $table) {
            $table->foreignId('related_collector_id')->nullable()->after('reference_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supervisor_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('related_collector_id');
        });
    }
};
