<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('tenant_resident_id', 8)->nullable()->after('resident_id');
            $table->string('billing_payer', 20)->default('pemilik')->after('tenant_resident_id');
            $table->foreign('tenant_resident_id')->references('id')->on('residents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropForeign(['tenant_resident_id']);
            $table->dropColumn(['tenant_resident_id', 'billing_payer']);
        });
    }
};
