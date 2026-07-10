<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cluster_rate_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('cluster_id', 2);
            $table->decimal('rate', 15, 2);
            $table->date('effective_date');
            $table->string('notes', 200)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('cluster_id')->references('id')->on('clusters');
            $table->index(['cluster_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_rate_schedules');
    }
};
