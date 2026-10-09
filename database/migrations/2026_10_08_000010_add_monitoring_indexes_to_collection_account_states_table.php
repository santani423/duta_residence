<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index tambahan untuk monitoring penagihan (GET /collection/accounts) yang mengurutkan &
 * memfilter berdasarkan skor prioritas, nominal tunggakan, dan umur tunggakan. Murni aditif.
 */
return new class extends Migration
{
    private const COLUMNS = ['priority_score', 'outstanding_total', 'aging_days'];

    public function up(): void
    {
        Schema::table('collection_account_states', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (! Schema::hasIndex('collection_account_states', $this->indexName($column))) {
                    $table->index($column, $this->indexName($column));
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('collection_account_states', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (Schema::hasIndex('collection_account_states', $this->indexName($column))) {
                    $table->dropIndex($this->indexName($column));
                }
            }
        });
    }

    private function indexName(string $column): string
    {
        return "collection_account_states_{$column}_index";
    }
};
