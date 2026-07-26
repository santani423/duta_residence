<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collector_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collector_id')->constrained('users')->restrictOnDelete();
            $table->string('scope_type', 20);
            $table->string('cluster_id', 2)->nullable();
            $table->string('block', 5)->nullable();
            $table->string('unit_id', 5)->nullable();
            $table->string('resident_id', 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('cluster_id')->references('id')->on('clusters')->restrictOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
            $table->foreign('resident_id')->references('id')->on('residents')->restrictOnDelete();
            $table->index(['collector_id', 'is_active']);
            // No DB-level unique index on the scope tuple: MySQL (this app's production driver)
            // has no partial/filtered index support, so a plain unique index would reject a new
            // active assignment that reuses a scope once revoked (is_active=false). Duplicate
            // active-scope prevention is enforced in CollectorAssignmentController instead,
            // mirroring the app's existing convention for conditional uniqueness (e.g. article
            // slugs, which also can't use a DB unique index because of soft deletes).
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_assignments');
    }
};
