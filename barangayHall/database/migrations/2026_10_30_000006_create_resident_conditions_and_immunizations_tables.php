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
        Schema::create('resident_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('condition_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->date('diagnosed_date')->nullable();
            $table->enum('status', ['active', 'controlled', 'resolved'])->default('active');
            $table->text('notes')->nullable(); // encrypted
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['resident_id', 'status']);
            // "Common conditions" report groups by condition and filters by status.
            $table->index(['condition_id', 'status']);
        });

        Schema::create('immunizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('vaccine_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedTinyInteger('dose_no');
            $table->date('date_given');
            $table->foreignId('given_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('lot_no', 40)->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // "Children due" anti-join: does this resident already have this dose?
            $table->index(['resident_id', 'vaccine_id', 'dose_no']);
            $table->index('date_given');
        });

        MysqlSchema::check('immunizations', 'immunizations_dose_no_check', 'dose_no BETWEEN 1 AND 10');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('immunizations');
        Schema::dropIfExists('resident_conditions');
    }
};
