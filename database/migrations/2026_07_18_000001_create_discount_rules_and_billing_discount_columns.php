<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 20); // 'fixed' (rupiah) atau 'percentage' (0-100)
            $table->decimal('value', 15, 2);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('units', function (Blueprint $table) {
            $table->foreignId('discount_rule_id')->nullable()->after('is_discount_eligible')
                ->constrained('discount_rules')->nullOnDelete();
        });

        Schema::table('billings', function (Blueprint $table) {
            $table->foreignId('discount_rule_id')->nullable()->after('discount')
                ->constrained('discount_rules')->nullOnDelete();
            $table->foreignId('discount_set_by')->nullable()->after('discount_rule_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('discount_set_at')->nullable()->after('discount_set_by');
            $table->string('discount_reason', 200)->nullable()->after('discount_set_at');
        });
    }

    public function down(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_set_by');
            $table->dropConstrainedForeignId('discount_rule_id');
            $table->dropColumn(['discount_set_at', 'discount_reason']);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_rule_id');
        });

        Schema::dropIfExists('discount_rules');
    }
};
