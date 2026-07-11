<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->string('identity_number', 30)->nullable()->after('name');
            $table->string('identity_type', 20)->nullable()->after('identity_number');
            $table->string('emergency_contact_name', 100)->nullable()->after('id_card_address');
            $table->string('emergency_contact_phone', 20)->nullable()->after('emergency_contact_name');
            $table->text('notes')->nullable()->after('emergency_contact_phone');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->string('occupancy_role', 20)->default('pemilik')->after('resident_id');
            $table->date('tenancy_start_date')->nullable()->after('handover_date');
            $table->date('tenancy_end_date')->nullable()->after('tenancy_start_date');
        });

        Schema::table('resident_complaints', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('division', 50)->nullable()->after('assigned_to');
            $table->date('target_date')->nullable()->after('division');
        });

        Schema::create('collector_visits', function (Blueprint $table) {
            $table->id();
            $table->string('unit_id', 5);
            $table->foreignId('collector_id')->constrained('users');
            $table->dateTime('visit_date');
            $table->string('purpose', 100);
            $table->text('result')->nullable();
            $table->string('met_with', 100)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('checkin_latitude', 10, 7)->nullable();
            $table->decimal('checkin_longitude', 10, 7)->nullable();
            $table->string('status', 20)->default('completed');
            $table->date('next_visit_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->index(['unit_id', 'visit_date']);
        });

        Schema::create('payment_promises', function (Blueprint $table) {
            $table->id();
            $table->string('unit_id', 5);
            $table->foreignId('billing_id')->nullable()->constrained('billings')->nullOnDelete();
            $table->decimal('promised_amount', 15, 2);
            $table->date('promised_date');
            $table->string('payment_method', 30)->nullable();
            $table->text('reason')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->index(['unit_id', 'status']);
        });

        Schema::create('resident_documents', function (Blueprint $table) {
            $table->id();
            $table->string('documentable_type');
            $table->string('documentable_id', 20);
            $table->string('document_type', 50);
            $table->string('document_number', 60)->nullable();
            $table->date('document_date')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('file_path');
            $table->string('original_filename')->nullable();
            $table->string('verification_status', 20)->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['documentable_type', 'documentable_id']);
        });

        Schema::create('unit_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('unit_id', 5);
            $table->string('vehicle_type', 20);
            $table->string('brand', 50)->nullable();
            $table->string('model', 50)->nullable();
            $table->string('color', 30)->nullable();
            $table->string('plate_number', 20);
            $table->string('sticker_number', 30)->nullable();
            $table->date('sticker_valid_until')->nullable();
            $table->string('owner_name', 100)->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->index('unit_id');
        });

        Schema::create('unit_occupants', function (Blueprint $table) {
            $table->id();
            $table->string('unit_id', 5);
            $table->string('type', 10);
            $table->string('name', 100);
            $table->string('relationship', 50)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('identity_number', 30)->nullable();
            $table->string('photo_path')->nullable();
            $table->date('start_date')->nullable();
            $table->date('access_valid_until')->nullable();
            $table->string('emergency_contact', 100)->nullable();
            $table->string('access_status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->index(['unit_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_occupants');
        Schema::dropIfExists('unit_vehicles');
        Schema::dropIfExists('resident_documents');
        Schema::dropIfExists('payment_promises');
        Schema::dropIfExists('collector_visits');

        Schema::table('resident_complaints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['division', 'target_date']);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['occupancy_role', 'tenancy_start_date', 'tenancy_end_date']);
        });

        Schema::table('residents', function (Blueprint $table) {
            $table->dropColumn(['identity_number', 'identity_type', 'emergency_contact_name', 'emergency_contact_phone', 'notes']);
        });
    }
};
