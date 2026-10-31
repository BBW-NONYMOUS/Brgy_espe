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
        // Notifiable disease list following DOH PIDSR categories; Admin-editable.
        Schema::create('diseases', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('icd10_code', 10)->nullable();
            // category_1 = immediately notifiable, category_2 = weekly, other = not notifiable.
            $table->enum('pidsr_category', ['category_1', 'category_2', 'other']);
            $table->boolean('is_notifiable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('disease_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('disease_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            // Where the case occurred, captured at reporting time. Not derived from the
            // resident's *current* household: moving house must not move past cases
            // to another purok's outbreak count.
            $table->foreignId('purok_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->date('onset_date');
            $table->date('reported_date');
            $table->enum('classification', ['suspected', 'probable', 'confirmed', 'ruled_out'])->default('suspected');
            $table->enum('outcome', ['ongoing', 'recovered', 'died'])->default('ongoing');
            $table->date('outcome_date')->nullable();
            $table->text('notes')->nullable(); // encrypted
            $table->foreignId('reported_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('classified_by')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('health_visit_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Trend chart / case list: one disease over time.
            $table->index(['disease_id', 'onset_date']);
            // Threshold check: cases of one disease, per purok, inside the window.
            $table->index(['disease_id', 'purok_id', 'onset_date']);
            $table->index(['resident_id', 'onset_date']);
            // All-disease date ranges: Officials' purok map, weekly report.
            $table->index(['onset_date', 'purok_id']);
        });

        Schema::create('outbreak_thresholds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disease_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('scope', ['purok', 'barangay']);
            $table->unsignedSmallInteger('min_cases');
            $table->unsignedSmallInteger('days_window');
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['disease_id', 'scope']);
        });

        Schema::create('outbreak_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disease_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            // NULL = barangay-wide alert.
            $table->foreignId('purok_id')->nullable()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            // Rule in force when the alert fired (thresholds can be edited later).
            $table->unsignedSmallInteger('threshold_min_cases');
            $table->unsignedSmallInteger('threshold_days_window');
            $table->unsignedSmallInteger('case_count');
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['active', 'monitoring', 'closed'])->default('active');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->string('closing_note')->nullable();
            $table->timestamps();

            // "Is there already an open alert for this disease + purok?" Leading
            // disease_id also serves the FK, so no separate disease_id index.
            $table->index(['disease_id', 'purok_id', 'status']);
            // Dashboard banner: all open alerts.
            $table->index('status');
        });

        MysqlSchema::check('disease_cases', 'disease_cases_dates_check', 'reported_date >= onset_date');
        MysqlSchema::check('disease_cases', 'disease_cases_outcome_check', "(outcome = 'ongoing') = (outcome_date IS NULL)");
        MysqlSchema::check('outbreak_thresholds', 'outbreak_thresholds_values_check', 'min_cases BETWEEN 1 AND 1000 AND days_window BETWEEN 1 AND 365');
        MysqlSchema::check('outbreak_alerts', 'outbreak_alerts_period_check', 'period_end >= period_start');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbreak_alerts');
        Schema::dropIfExists('outbreak_thresholds');
        Schema::dropIfExists('disease_cases');
        Schema::dropIfExists('diseases');
    }
};
