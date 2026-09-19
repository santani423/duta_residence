<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_settings', function (Blueprint $table) {
            $table->string('admin_discount_type', 15)->default('percentage')->after('maximum_admin_discount');
        });
    }

    public function down(): void
    {
        Schema::table('discount_settings', function (Blueprint $table) {
            $table->dropColumn('admin_discount_type');
        });
    }
};
