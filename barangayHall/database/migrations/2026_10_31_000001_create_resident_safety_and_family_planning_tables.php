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
        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('relationship', 40);
            $table->string('phone', 20);
            // At most one primary per resident — enforced in the service inside a
            // transaction (MySQL has no partial unique index).
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['resident_id', 'is_primary']);
        });

        Schema::create('medical_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            // ENUMs: the red banner and form warnings branch on type and severity.
            $table->enum('alert_type', ['allergy', 'chronic', 'disability', 'other']);
            $table->text('description'); // encrypted (sensitive personal information)
            $table->enum('severity', ['info', 'warning', 'critical'])->default('warning');
            $table->boolean('is_active')->default(true);
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // "Active critical alerts for this resident" on every profile and visit form.
            $table->index(['resident_id', 'is_active', 'severity']);
        });

        // Lookup: DOH Family Planning Program methods.
        Schema::create('family_planning_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->enum('category', ['modern_artificial', 'modern_natural', 'traditional']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Method history per resident (only recorded with a family_planning consent).
        Schema::create('resident_family_planning', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('family_planning_method_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            // Current method = row with ended_on NULL.
            $table->index(['resident_id', 'ended_on']);
        });

        MysqlSchema::check('resident_family_planning', 'resident_family_planning_dates_check', 'ended_on IS NULL OR ended_on >= started_on');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resident_family_planning');
        Schema::dropIfExists('family_planning_methods');
        Schema::dropIfExists('medical_alerts');
        Schema::dropIfExists('emergency_contacts');
    }
};
