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
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            // CASCADE: consent rows only disappear when the resident is permanently deleted (retention policy).
            $table->foreignId('resident_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            // ENUM: the application branches on these (geo-tagging and family planning
            // data are refused without the matching consent; SMS needs its own).
            $table->enum('consent_type', ['health_profiling', 'geotagging', 'family_planning', 'sms_reminders'])
                ->default('health_profiling');
            $table->date('consent_date');
            $table->foreignId('obtained_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('purpose');
            $table->date('revoked_at')->nullable();
            $table->timestamps();

            // "Does this resident have an active consent of type X?"
            $table->index(['resident_id', 'consent_type', 'revoked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
