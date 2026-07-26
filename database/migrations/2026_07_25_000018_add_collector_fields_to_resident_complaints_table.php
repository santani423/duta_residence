<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resident_complaints', function (Blueprint $table) {
            $table->foreignId('collector_id')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->foreignId('related_visit_id')->nullable()->after('collector_id')->constrained('collector_visits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('resident_complaints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('related_visit_id');
            $table->dropConstrainedForeignId('collector_id');
        });
    }
};
