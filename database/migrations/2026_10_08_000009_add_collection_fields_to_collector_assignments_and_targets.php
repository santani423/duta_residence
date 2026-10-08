<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collector_assignments', function (Blueprint $table) {
            $table->foreignId('reassigned_from_id')->nullable()->after('assigned_by')->constrained('collector_assignments')->nullOnDelete();
            $table->text('reassign_reason')->nullable()->after('reassigned_from_id');
        });

        Schema::table('collector_targets', function (Blueprint $table) {
            $table->unsignedInteger('target_account_count')->nullable()->after('target_visit_count');
            $table->decimal('target_collection_rate', 5, 2)->nullable()->after('target_account_count');
        });
    }

    public function down(): void
    {
        Schema::table('collector_targets', function (Blueprint $table) {
            $table->dropColumn(['target_account_count', 'target_collection_rate']);
        });

        Schema::table('collector_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reassigned_from_id');
            $table->dropColumn('reassign_reason');
        });
    }
};
