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
        Schema::create('appointment_types', function (Blueprint $table) {
            $table->id();
            // Stable code used by the generator (prenatal, immunization, ...); name is editable.
            $table->string('code', 30)->unique();
            $table->string('name', 60);
            $table->string('color', 7); // calendar colour, #RRGGBB
            $table->unsignedSmallInteger('default_duration_minutes')->default(30);
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('appointment_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->dateTime('scheduled_at');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->enum('status', ['scheduled', 'completed', 'missed', 'cancelled'])->default('scheduled');
            // Auto-generated appointments: source = Pregnancy or Resident (EPI), plus a key
            // naming the rule ("anc:3", "pnc:1", "epi:PENTA:2"). The unique index makes
            // the generator idempotent; manual appointments leave all three NULL.
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_key', 40)->nullable();
            // Set when staff reschedule/edit an auto-generated appointment, so the
            // generator never overwrites a human decision.
            $table->boolean('edited_manually')->default(false);
            // The visit that fulfilled it.
            $table->foreignId('health_visit_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->text('notes')->nullable(); // encrypted
            $table->timestamp('reminder_sent_at')->nullable(); // future SMS gateway
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'source_key']);
            // Calendar range + "today's appointments" + missed sweep.
            $table->index(['scheduled_at', 'status']);
            $table->index(['status', 'scheduled_at']);
            $table->index(['resident_id', 'scheduled_at']);
            $table->index(['assigned_to', 'scheduled_at']);
        });

        Schema::create('program_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('color', 7);
            $table->timestamps();
        });

        Schema::create('health_programs', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->foreignId('program_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->text('description')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('venue', 150);
            // ENUM: drives "add all eligible residents" to the attendance list.
            $table->enum('target_group', [
                'everyone', 'infants_0_11m', 'children_0_59m', 'school_age', 'adolescents',
                'women_15_49', 'pregnant', 'seniors', 'pwd', 'households',
            ])->default('everyone');
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Shared calendar range query.
            $table->index(['start_at', 'status']);
        });

        Schema::create('program_puroks', function (Blueprint $table) {
            $table->foreignId('health_program_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('purok_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->primary(['health_program_id', 'purok_id']);
        });

        Schema::create('program_staff', function (Blueprint $table) {
            $table->foreignId('health_program_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->primary(['health_program_id', 'user_id']);
        });

        Schema::create('program_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('health_program_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->dateTime('attended_at');
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['health_program_id', 'resident_id']);
            $table->index('resident_id');
        });

        MysqlSchema::check('appointment_types', 'appointment_types_color_check', "color REGEXP '^#[0-9A-Fa-f]{6}$'");
        MysqlSchema::check('program_types', 'program_types_color_check', "color REGEXP '^#[0-9A-Fa-f]{6}$'");
        MysqlSchema::check('appointments', 'appointments_source_check', '(source_type IS NULL) = (source_key IS NULL)');
        MysqlSchema::check('health_programs', 'health_programs_period_check', 'end_at > start_at');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_attendance');
        Schema::dropIfExists('program_staff');
        Schema::dropIfExists('program_puroks');
        Schema::dropIfExists('health_programs');
        Schema::dropIfExists('program_types');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('appointment_types');
    }
};
