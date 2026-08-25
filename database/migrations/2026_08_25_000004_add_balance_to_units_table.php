<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->decimal('balance', 15, 2)->default(0)->after('discount_rule_id');
        });

        // Backfill from each unit's latest unit_deposits row so already-created dev-data
        // credit (from before this cached column existed) is not lost. Done via the query
        // builder (not a raw UPDATE...JOIN) so it works on both MySQL (prod/dev) and SQLite
        // (the test suite's in-memory driver).
        DB::table('unit_deposits')
            ->select('unit_id', DB::raw('MAX(id) as last_id'))
            ->groupBy('unit_id')
            ->get()
            ->each(function ($row) {
                $balanceAfter = DB::table('unit_deposits')->where('id', $row->last_id)->value('balance_after');
                DB::table('units')->where('id', $row->unit_id)->update(['balance' => $balanceAfter]);
            });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }
};
