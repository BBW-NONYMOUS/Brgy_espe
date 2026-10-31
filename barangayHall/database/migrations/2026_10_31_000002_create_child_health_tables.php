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
        // Operation Timbang / growth monitoring. Kept apart from vital_signs:
        // weighing sessions happen outside consultations (no health_visit).
        Schema::create('growth_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('measured_on');
            $table->decimal('weight_kg', 5, 2);
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->decimal('muac_cm', 4, 1)->nullable();
            // Picked by the BHW from the OPT Plus chart (not auto-computed).
            $table->enum('nutritional_status', [
                'normal', 'underweight', 'severely_underweight', 'stunted', 'severely_stunted',
                'wasted', 'severely_wasted', 'overweight', 'obese',
            ])->nullable();
            $table->foreignId('measured_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['resident_id', 'measured_on']);
            // Malnutrition report: latest status per child within a period.
            $table->index(['measured_on', 'nutritional_status']);
        });

        Schema::create('micronutrient_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            // ENUM: DOH programs with fixed rules (vitamin A every 6 months, etc.).
            $table->enum('type', ['vitamin_a', 'deworming', 'iron', 'micronutrient_powder']);
            $table->string('dose', 100)->nullable(); // "100,000 IU", "Albendazole 400 mg"
            $table->date('date_given');
            $table->foreignId('given_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // "Last vitamin A for this child" and per-program reports.
            $table->index(['resident_id', 'type', 'date_given']);
            $table->index(['type', 'date_given']);
        });

        MysqlSchema::check('growth_measurements', 'growth_measurements_weight_check', 'weight_kg BETWEEN 0.5 AND 150');
        MysqlSchema::check('growth_measurements', 'growth_measurements_height_check', 'height_cm IS NULL OR height_cm BETWEEN 30 AND 200');
        MysqlSchema::check('growth_measurements', 'growth_measurements_muac_check', 'muac_cm IS NULL OR muac_cm BETWEEN 5 AND 40');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('micronutrient_records');
        Schema::dropIfExists('growth_measurements');
    }
};
