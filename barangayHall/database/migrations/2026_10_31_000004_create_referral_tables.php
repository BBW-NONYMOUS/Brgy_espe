<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->enum('type', ['rhu', 'hospital', 'other']);
            $table->string('address')->nullable();
            $table->string('contact_no', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            // Printed on the slip: REF-2026-00001.
            $table->string('referral_no', 20)->unique();
            $table->foreignId('resident_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            // Optional: the consultation that led to the referral (vitals on the slip).
            $table->foreignId('health_visit_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('referred_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->dateTime('referred_at');
            $table->text('reason'); // encrypted
            $table->enum('urgency', ['routine', 'urgent', 'emergency'])->default('routine');
            $table->enum('status', ['pending', 'sent', 'received', 'completed', 'cancelled'])->default('pending');
            $table->text('feedback_note')->nullable(); // encrypted — return slip from the facility
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Referral list: open referrals first, newest first.
            $table->index(['status', 'referred_at']);
            $table->index(['resident_id', 'referred_at']);
        });

        // Append-only trail behind the status stepper (who moved it, when).
        Schema::create('referral_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('from_status', ['pending', 'sent', 'received', 'completed', 'cancelled'])->nullable();
            $table->enum('to_status', ['pending', 'sent', 'received', 'completed', 'cancelled']);
            $table->foreignId('changed_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['referral_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_status_changes');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('facilities');
    }
};
