<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collector_visit_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('collector_visits')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('file_path')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->dateTime('captured_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['visit_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_visit_evidence');
    }
};
