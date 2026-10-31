<?php

use App\Support\MysqlSchema;
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
        Schema::create('health_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('visit_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->date('visit_date');
            $table->foreignId('attended_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            // Encrypted (sensitive personal information under RA 10173).
            $table->text('chief_complaint')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Per-resident timeline, newest first.
            $table->index(['resident_id', 'visit_date']);
            // Date-range reports across all residents.
            $table->index(['visit_date', 'visit_type_id']);
        });

        Schema::create('vital_signs', function (Blueprint $table) {
            $table->id();
            // 1:1 optional — not every visit records vitals.
            $table->foreignId('health_visit_id')->unique()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedSmallInteger('bp_systolic')->nullable();
            $table->unsignedSmallInteger('bp_diastolic')->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->decimal('temperature_c', 4, 1)->nullable();
            $table->unsignedSmallInteger('pulse_rate')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->timestamps();
        });

        MysqlSchema::check('vital_signs', 'vital_signs_bp_systolic_check', 'bp_systolic IS NULL OR bp_systolic BETWEEN 50 AND 300');
        MysqlSchema::check('vital_signs', 'vital_signs_bp_diastolic_check', 'bp_diastolic IS NULL OR bp_diastolic BETWEEN 30 AND 200');
        MysqlSchema::check('vital_signs', 'vital_signs_bp_pair_check', 'bp_systolic IS NULL OR bp_diastolic IS NULL OR bp_systolic > bp_diastolic');
        MysqlSchema::check('vital_signs', 'vital_signs_weight_check', 'weight_kg IS NULL OR weight_kg BETWEEN 0.5 AND 500');
        MysqlSchema::check('vital_signs', 'vital_signs_height_check', 'height_cm IS NULL OR height_cm BETWEEN 20 AND 272');
        MysqlSchema::check('vital_signs', 'vital_signs_temperature_check', 'temperature_c IS NULL OR temperature_c BETWEEN 30 AND 45');
        MysqlSchema::check('vital_signs', 'vital_signs_pulse_check', 'pulse_rate IS NULL OR pulse_rate BETWEEN 20 AND 250');
        MysqlSchema::check('vital_signs', 'vital_signs_respiratory_check', 'respiratory_rate IS NULL OR respiratory_rate BETWEEN 5 AND 80');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vital_signs');
        Schema::dropIfExists('health_visits');
    }
};
