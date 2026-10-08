<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visit terjadwal & siklus hidup (scheduled → in_progress → completed/failed/cancelled).
 * `lifecycle` default `completed` supaya seluruh data visit lama (selalu dicatat setelah
 * terjadi) tetap bermakna tanpa backfill. Kolom `status` lama dipertahankan untuk kompatibilitas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collector_visits', function (Blueprint $table) {
            $table->date('scheduled_date')->nullable()->after('visit_date');
            $table->time('scheduled_time')->nullable()->after('scheduled_date');
            $table->string('priority', 20)->nullable()->after('purpose');
            $table->string('lifecycle', 20)->default('completed')->after('status');
            $table->string('result_code', 30)->nullable()->after('lifecycle');
            $table->dateTime('started_at')->nullable()->after('result_code');
            $table->dateTime('finished_at')->nullable()->after('started_at');
            $table->decimal('start_latitude', 10, 7)->nullable()->after('finished_at');
            $table->decimal('start_longitude', 10, 7)->nullable()->after('start_latitude');
            $table->uuid('client_uuid')->nullable()->unique()->after('next_visit_date');
            $table->unsignedInteger('version')->default(1)->after('client_uuid');
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();

            $table->index(['collector_id', 'lifecycle', 'scheduled_date']);
        });
    }

    public function down(): void
    {
        Schema::table('collector_visits', function (Blueprint $table) {
            $table->dropIndex(['collector_id', 'lifecycle', 'scheduled_date']);
            $table->dropConstrainedForeignId('updated_by');
            $table->dropUnique(['client_uuid']);
            $table->dropColumn([
                'scheduled_date', 'scheduled_time', 'priority', 'lifecycle', 'result_code',
                'started_at', 'finished_at', 'start_latitude', 'start_longitude', 'client_uuid', 'version',
            ]);
        });
    }
};
