<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_book_sections', function (Blueprint $table) {
            $table->id();
            $table->string('module', 60);
            $table->string('slug', 120);
            $table->string('title', 200);
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();
            $table->json('steps')->nullable();
            $table->json('tips')->nullable();
            $table->json('warnings')->nullable();
            $table->json('faqs')->nullable();
            $table->json('roles')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['module', 'slug']);
            $table->index(['module', 'order']);
        });

        Schema::create('manual_book_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manual_book_section_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->unique(['user_id', 'manual_book_section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_book_reads');
        Schema::dropIfExists('manual_book_sections');
    }
};
