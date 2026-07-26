<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_letters', function (Blueprint $table) {
            $table->id();
            $table->string('unit_id', 5);
            $table->string('resident_id', 8);
            $table->foreignId('billing_id')->nullable()->constrained('billings')->nullOnDelete();
            $table->string('letter_type', 20);
            $table->text('content');
            $table->string('pdf_path')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('generated_at');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
            $table->foreign('resident_id')->references('id')->on('residents')->restrictOnDelete();
            $table->index(['unit_id', 'letter_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_letters');
    }
};
