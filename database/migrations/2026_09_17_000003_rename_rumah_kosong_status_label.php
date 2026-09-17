<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('resident_statuses')->where('id', 'RK')->update(['name' => 'Properti Kosong']);
    }

    public function down(): void
    {
        DB::table('resident_statuses')->where('id', 'RK')->update(['name' => 'Rumah Kosong']);
    }
};
