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
        Schema::create('puroks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->unsignedTinyInteger('sort_order')->default(0);
            // Admin-set map centre for the Officials' purok-level map. Never an
            // average of household points (a lone household would be revealed).
            $table->decimal('center_latitude', 10, 7)->nullable();
            $table->decimal('center_longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        // Family health profile lookups (DOH water supply levels / sanitation categories).
        Schema::create('water_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            // Level I–III and protected sources count as "safe" in reports.
            $table->boolean('is_safe')->default(false);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('toilet_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->boolean('is_sanitary')->default(false);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('household_no', 20)->unique();
            // RESTRICT: a purok with households cannot be removed by accident.
            $table->foreignId('purok_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('address');
            // head_resident_id FK is added after `residents` exists (circular reference).
            $table->unsignedBigInteger('head_resident_id')->nullable();
            $table->foreignId('water_source_id')->nullable()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('toilet_type_id')->nullable()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            // 4Ps (Pantawid Pamilyang Pilipino Program) enrols households, not individuals.
            $table->boolean('is_4ps')->default(false);

            // Geo-tag. DECIMAL(10,7) ≈ 1 cm precision, exact (no float rounding).
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('geotag_accuracy_m')->nullable();
            $table->timestamp('geotagged_at')->nullable();
            $table->foreignId('geotagged_by')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            // Bounding-box lookups ("households near me", map viewport).
            $table->index(['latitude', 'longitude']);
        });

        MysqlSchema::check('puroks', 'puroks_center_pair_check', '(center_latitude IS NULL) = (center_longitude IS NULL)');
        MysqlSchema::check('households', 'households_geotag_pair_check', '(latitude IS NULL) = (longitude IS NULL) AND (latitude IS NULL) = (geotagged_at IS NULL)');
        MysqlSchema::check('households', 'households_latitude_check', 'latitude IS NULL OR latitude BETWEEN -90 AND 90');
        MysqlSchema::check('households', 'households_longitude_check', 'longitude IS NULL OR longitude BETWEEN -180 AND 180');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('households');
        Schema::dropIfExists('toilet_types');
        Schema::dropIfExists('water_sources');
        Schema::dropIfExists('puroks');
    }
};
