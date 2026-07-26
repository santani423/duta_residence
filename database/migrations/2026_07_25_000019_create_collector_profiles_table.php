<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collector_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('collector_code', 20)->unique();
            $table->string('whatsapp_number', 30)->nullable();
            $table->text('address')->nullable();
            $table->date('joined_at')->nullable();
            $table->string('employment_status', 20)->default('tetap');
            $table->string('account_status', 20)->default('active');
            $table->text('working_area_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_profiles');
    }
};
