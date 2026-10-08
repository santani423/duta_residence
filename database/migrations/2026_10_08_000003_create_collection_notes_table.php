<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Catatan internal penagihan - tidak pernah diekspos ke endpoint resident. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_notes', function (Blueprint $table) {
            $table->id();
            $table->string('unit_id', 5);
            $table->string('title', 150);
            $table->text('body');
            $table->string('priority', 20)->default('normal');
            $table->uuid('client_uuid')->nullable()->unique();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->index(['unit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_notes');
    }
};
