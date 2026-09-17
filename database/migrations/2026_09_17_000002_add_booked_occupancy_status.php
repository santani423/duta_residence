<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('occupancy_statuses')->updateOrInsert(['id' => '4'], ['name' => 'Booked']);
    }

    public function down(): void
    {
        DB::table('occupancy_statuses')->where('id', '4')->delete();
    }
};
