<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 20)->unique();
            $table->string('type', 30);
            $table->string('requestable_type');
            $table->unsignedBigInteger('requestable_id');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('related_collector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('related_unit_id', 5)->nullable();
            $table->string('related_resident_id', 8)->nullable();
            $table->json('before_value')->nullable();
            $table->json('after_value')->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('submitted');
            $table->text('supervisor_notes')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('related_unit_id')->references('id')->on('units')->nullOnDelete();
            $table->foreign('related_resident_id')->references('id')->on('residents')->nullOnDelete();
            $table->index(['requestable_type', 'requestable_id']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
