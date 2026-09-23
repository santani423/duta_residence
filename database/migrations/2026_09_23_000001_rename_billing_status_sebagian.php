<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('billing_statuses')->where('id', '03')->update(['name' => 'Sudah Bayar']);
    }

    public function down(): void
    {
        DB::table('billing_statuses')->where('id', '03')->update(['name' => 'Sebagian']);
    }
};
