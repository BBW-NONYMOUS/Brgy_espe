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
        Schema::create('visit_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->timestamps();
        });

        Schema::create('conditions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('icd10_code', 10)->nullable();
            $table->timestamps();
        });

        Schema::create('vaccines', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->unsignedTinyInteger('doses_required')->default(1);
            $table->timestamps();
        });

        // DOH Expanded Program on Immunization schedule: when each dose is due.
        // Editable by the Midwife/Admin (Settings), never hard-coded.
        Schema::create('vaccine_schedule_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vaccine_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedTinyInteger('dose_no');
            $table->unsignedSmallInteger('due_age_days');
            // Minimum gap after the previous dose (e.g. Penta 2 ≥ 28 days after Penta 1),
            // so a late dose 1 pushes dose 2 back instead of making it "overdue".
            $table->unsignedSmallInteger('min_interval_days')->nullable();
            $table->timestamps();

            $table->unique(['vaccine_id', 'dose_no']);
            $table->index('due_age_days');
        });

        MysqlSchema::check('vaccines', 'vaccines_doses_required_check', 'doses_required BETWEEN 1 AND 10');
        MysqlSchema::check('vaccine_schedule_rules', 'vaccine_schedule_rules_dose_no_check', 'dose_no BETWEEN 1 AND 10');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vaccine_schedule_rules');
        Schema::dropIfExists('vaccines');
        Schema::dropIfExists('conditions');
        Schema::dropIfExists('visit_types');
    }
};
