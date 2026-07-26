<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_id')->constrained('users')->restrictOnDelete();
            $table->string('cluster_id', 2);
            $table->boolean('is_active')->default(true);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('cluster_id')->references('id')->on('clusters')->restrictOnDelete();
            $table->index(['supervisor_id', 'is_active']);
            // No DB-level unique index on (supervisor_id, cluster_id): same reasoning as
            // collector_assignments - MySQL has no partial index support, so duplicate-active
            // prevention for a revoked-then-reassigned cluster is enforced in the controller.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_assignments');
    }
};
