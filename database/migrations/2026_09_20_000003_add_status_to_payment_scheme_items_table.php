<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_scheme_items', function (Blueprint $table) {
            // 'rejected' = Admin dropped this month from the scheme while approving it.
            $table->string('status', 20)->default('included')->after('billing_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_scheme_items', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
