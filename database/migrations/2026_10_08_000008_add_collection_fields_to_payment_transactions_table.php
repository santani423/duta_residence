<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kolom untuk pembayaran yang dikumpulkan collector di lapangan beserta alur verifikasinya. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->foreignId('collected_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->dateTime('collected_at')->nullable()->after('collected_by');
            $table->decimal('collection_latitude', 10, 7)->nullable()->after('collected_at');
            $table->decimal('collection_longitude', 10, 7)->nullable()->after('collection_latitude');
            $table->text('revision_notes')->nullable()->after('verification_notes');
            $table->timestamp('revision_requested_at')->nullable()->after('revision_notes');
            $table->text('rejection_reason')->nullable()->after('revision_requested_at');
            $table->uuid('client_uuid')->nullable()->unique()->after('provider_payload');
            $table->unsignedInteger('version')->default(1)->after('client_uuid');

            $table->index(['collected_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropIndex(['collected_by', 'status']);
            $table->dropConstrainedForeignId('collected_by');
            $table->dropUnique(['client_uuid']);
            $table->dropColumn([
                'collected_at', 'collection_latitude', 'collection_longitude', 'revision_notes',
                'revision_requested_at', 'rejection_reason', 'client_uuid', 'version',
            ]);
        });
    }
};
