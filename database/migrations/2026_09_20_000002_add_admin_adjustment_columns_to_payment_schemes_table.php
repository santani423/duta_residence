<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_schemes', function (Blueprint $table) {
            // Set only when Admin changed the loket's request while approving: who, when, and what
            // the loket originally asked for (so both the requested and the approved amounts are kept).
            $table->json('requested_snapshot')->nullable()->after('final_amount');
            $table->foreignId('adjusted_by')->nullable()->after('requested_snapshot')->constrained('users')->restrictOnDelete();
            $table->timestamp('adjusted_at')->nullable()->after('adjusted_by');
        });
    }

    public function down(): void
    {
        Schema::table('payment_schemes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('adjusted_by');
            $table->dropColumn(['requested_snapshot', 'adjusted_at']);
        });
    }
};
