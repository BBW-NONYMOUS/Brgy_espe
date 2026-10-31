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
        // Lookup: DOH high-risk pregnancy factors. Admin can add more.
        Schema::create('risk_factors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Configurable prenatal / postnatal contact schedule (Midwife/Admin).
        // Prenatal offset = age of gestation in days; postnatal = days after delivery.
        Schema::create('maternal_visit_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('kind', ['prenatal', 'postnatal']);
            $table->unsignedTinyInteger('sequence');
            $table->string('label', 60);
            $table->unsignedSmallInteger('offset_days');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['kind', 'sequence']);
        });

        // One row per pregnancy.
        Schema::create('pregnancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('lmp')->nullable();
            // Stored, not derived: ultrasound dating may override LMP + 280 days.
            $table->date('edd');
            $table->unsignedTinyInteger('gravida');
            $table->unsignedTinyInteger('para');
            $table->enum('risk_level', ['low', 'high'])->default('low');
            $table->enum('status', ['ongoing', 'delivered', 'terminated'])->default('ongoing');
            // Date the pregnancy ended (delivery or loss). Stored once here; the
            // 1:1 deliveries row holds the delivery details, not the date again.
            $table->date('outcome_date')->nullable();
            $table->text('notes')->nullable(); // encrypted
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['resident_id', 'status']);
            // Maternal tracker / "pregnant women" report: ongoing pregnancies by due date.
            $table->index(['status', 'edd']);
        });

        Schema::create('pregnancy_risk_factor', function (Blueprint $table) {
            $table->foreignId('pregnancy_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('risk_factor_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->primary(['pregnancy_id', 'risk_factor_id']);
        });

        // Date, attending staff, BP and weight live on the linked health_visit /
        // vital_signs rows, so they are not repeated here (3NF). Age of gestation
        // is computed from the pregnancy's EDD and the visit date.
        Schema::create('prenatal_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pregnancy_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('health_visit_id')->unique()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            // Which scheduled contact this visit fulfils (NULL = extra visit).
            $table->foreignId('maternal_visit_rule_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->decimal('fundal_height_cm', 4, 1)->nullable();
            $table->unsignedSmallInteger('fetal_heart_rate')->nullable();
            $table->text('remarks')->nullable(); // encrypted findings
            $table->timestamps();

            $table->index(['pregnancy_id', 'maternal_visit_rule_id']);
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pregnancy_id')->unique()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->time('delivery_time')->nullable();
            // ENUMs: DOH facility-based delivery / skilled birth attendant indicators group on these.
            $table->enum('place_type', ['health_facility', 'birthing_home', 'home', 'in_transit', 'other']);
            $table->string('place_name', 150)->nullable();
            $table->enum('attendant_type', ['doctor', 'nurse', 'midwife', 'hilot', 'other']);
            $table->enum('delivery_mode', ['normal', 'cesarean', 'assisted']);
            // Counts instead of a single child FK: supports twins. Each live-born baby
            // is a resident with mother_id = this mother and birthdate = outcome_date.
            $table->unsignedTinyInteger('live_births')->default(1);
            $table->unsignedTinyInteger('stillbirths')->default(0);
            $table->text('remarks')->nullable(); // encrypted
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            $table->index('place_type');
        });

        Schema::create('postnatal_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pregnancy_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('health_visit_id')->unique()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('maternal_visit_rule_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->enum('breastfeeding', ['exclusive', 'mixed', 'none'])->nullable();
            $table->boolean('family_planning_counseled')->default(false);
            $table->text('remarks')->nullable(); // encrypted findings
            $table->timestamps();

            $table->index(['pregnancy_id', 'maternal_visit_rule_id']);
        });

        MysqlSchema::check('maternal_visit_rules', 'maternal_visit_rules_offset_check', 'offset_days <= 300');
        MysqlSchema::check('pregnancies', 'pregnancies_para_check', 'para <= gravida');
        MysqlSchema::check('pregnancies', 'pregnancies_gravida_check', 'gravida BETWEEN 1 AND 20');
        MysqlSchema::check('pregnancies', 'pregnancies_dates_check', 'lmp IS NULL OR edd > lmp');
        MysqlSchema::check('pregnancies', 'pregnancies_outcome_check', "(status = 'ongoing') = (outcome_date IS NULL)");
        MysqlSchema::check('prenatal_visits', 'prenatal_visits_fhr_check', 'fetal_heart_rate IS NULL OR fetal_heart_rate BETWEEN 60 AND 220');
        MysqlSchema::check('prenatal_visits', 'prenatal_visits_fundal_check', 'fundal_height_cm IS NULL OR fundal_height_cm BETWEEN 0 AND 60');
        MysqlSchema::check('deliveries', 'deliveries_births_check', 'live_births + stillbirths BETWEEN 1 AND 5');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postnatal_visits');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('prenatal_visits');
        Schema::dropIfExists('pregnancy_risk_factor');
        Schema::dropIfExists('pregnancies');
        Schema::dropIfExists('maternal_visit_rules');
        Schema::dropIfExists('risk_factors');
    }
};
