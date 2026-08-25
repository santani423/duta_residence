<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payment_methods')->updateOrInsert(['id' => 'S'], ['name' => 'Saldo']);
    }

    public function down(): void
    {
        DB::table('payment_methods')->where('id', 'S')->delete();
    }
};
