<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu baris per scope: 'global' (scope_key null, saklar utama), atau override
     * per 'role' | 'module' | 'page' | 'component' (scope_key = nama role/slug modul/
     * path halaman/kode komponen). Resolusi visibilitas ikon info memakai override
     * paling spesifik yang ditemukan, dan jatuh ke baris global bila tidak ada override.
     */
    public function up(): void
    {
        Schema::create('help_settings', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type', 20);
            $table->string('scope_key', 150)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['scope_type', 'scope_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_settings');
    }
};
