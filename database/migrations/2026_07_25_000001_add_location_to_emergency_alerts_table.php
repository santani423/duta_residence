<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergency_alerts', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('note');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->decimal('accuracy_meters', 8, 2)->nullable()->after('longitude');
            $table->timestamp('location_captured_at')->nullable()->after('accuracy_meters');
            $table->foreignId('cancelled_by')->nullable()->after('acknowledged_at')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('emergency_alerts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn([
                'latitude', 'longitude', 'accuracy_meters', 'location_captured_at', 'cancelled_at',
            ]);
        });
    }
};
