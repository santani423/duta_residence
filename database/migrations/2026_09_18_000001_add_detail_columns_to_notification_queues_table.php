<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_queues', function (Blueprint $table) {
            $table->string('title', 200)->nullable()->after('type');
            $table->foreignId('sender_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            // Polymorphic-by-convention pointer to the resource the notification is about
            // (e.g. App\Models\PaymentTransaction / 12). Kept as plain strings, not a FK,
            // so a deleted resource never deletes or breaks the notification itself.
            $table->string('reference_type', 100)->nullable()->after('message');
            $table->string('reference_id', 50)->nullable()->after('reference_type');
            $table->json('data')->nullable()->after('reference_id');
            $table->timestamp('read_at')->nullable()->after('read_status');

            $table->index(['user_id', 'read_status']);
            $table->index(['unit_id', 'read_status']);
        });
    }

    public function down(): void
    {
        Schema::table('notification_queues', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'read_status']);
            $table->dropIndex(['unit_id', 'read_status']);
            $table->dropConstrainedForeignId('sender_id');
            $table->dropColumn(['title', 'reference_type', 'reference_id', 'data', 'read_at']);
        });
    }
};
