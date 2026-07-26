<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collector_assignments', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('is_active');
            $table->date('end_date')->nullable()->after('start_date');
            $table->string('status', 20)->default('active')->after('end_date');
            $table->string('priority', 20)->default('normal')->after('status');
            $table->text('notes')->nullable()->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('collector_assignments', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date', 'status', 'priority', 'notes']);
        });
    }
};
