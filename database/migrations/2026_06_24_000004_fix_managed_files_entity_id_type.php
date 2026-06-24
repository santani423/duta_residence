<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('managed_files', function (Blueprint $table) {
            $table->string('entity_id', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('managed_files', function (Blueprint $table) {
            $table->unsignedBigInteger('entity_id')->nullable()->change();
        });
    }
};
