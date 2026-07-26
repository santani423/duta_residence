<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->string('format', 10)->default('pdf');
            $table->string('status', 20)->default('queued');
            $table->json('filters')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->text('failed_reason')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
