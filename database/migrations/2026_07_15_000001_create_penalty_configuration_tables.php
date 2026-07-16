<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penalty_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('cluster_id', 2)->nullable();
            $table->unsignedSmallInteger('minimum_overdue_month');
            $table->unsignedSmallInteger('maximum_overdue_month')->nullable();
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('effective_start_date');
            $table->date('effective_end_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('cluster_id')->references('id')->on('clusters')->nullOnDelete();
            $table->index(['cluster_id', 'is_active', 'effective_start_date']);
        });

        Schema::create('penalty_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->string('allocation_order', 20)->default('penalty_first');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Status baru untuk tagihan: dibayar sebagian (03) dan dibatalkan (04).
        // 01/02 (Belum Bayar/Lunas) sudah ada dan tetap dipakai apa adanya.
        DB::table('billing_statuses')->insert([
            ['id' => '03', 'name' => 'Sebagian'],
            ['id' => '04', 'name' => 'Dibatalkan'],
        ]);

        Schema::table('billings', function (Blueprint $table) {
            $table->decimal('principal_paid', 15, 2)->default(0)->after('amount');
            $table->decimal('penalty_paid', 15, 2)->default(0)->after('penalty');
            $table->decimal('penalty_waived_amount', 15, 2)->default(0)->after('penalty_paid');
            $table->unsignedSmallInteger('penalty_notified_tier')->nullable()->after('penalty_waived_amount');
            $table->timestamp('cancelled_at')->nullable()->after('paid_at');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 200)->nullable()->after('cancelled_by');
            $table->index(['status_id', 'year', 'month']);
        });

        // Backfill data lama: tagihan yang sudah lunas dianggap principal_paid = amount - discount
        // (mengikuti definisi principal_amount di PenaltyService) dan penalty_paid = penalty
        // yang sudah tersimpan, supaya laporan histori tetap konsisten.
        DB::table('billings')->where('status_id', '02')->update([
            'principal_paid' => DB::raw('amount - discount'),
            'penalty_paid' => DB::raw('penalty'),
        ]);
    }

    public function down(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->dropIndex(['status_id', 'year', 'month']);
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn([
                'principal_paid', 'penalty_paid', 'penalty_waived_amount',
                'penalty_notified_tier', 'cancelled_at', 'cancellation_reason',
            ]);
        });

        DB::table('billing_statuses')->whereIn('id', ['03', '04'])->delete();

        Schema::dropIfExists('penalty_settings');
        Schema::dropIfExists('penalty_rules');
    }
};
