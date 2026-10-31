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
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->string('resident_code', 20)->unique();
            // RESTRICT: a household cannot be deleted while residents (even archived) belong to it.
            $table->foreignId('household_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('last_name', 100);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('suffix', 10)->nullable();
            $table->enum('sex', ['male', 'female']);
            $table->date('birthdate');
            $table->string('birthplace', 150)->nullable();
            $table->enum('civil_status', ['single', 'married', 'widowed', 'separated', 'live_in']);
            $table->string('contact_no', 20)->nullable();
            // Encrypted ciphertext (~200 chars) — TEXT, never indexed or searched.
            $table->text('philhealth_no')->nullable();
            $table->boolean('is_pwd')->default(false);
            // ENUM: the eight ABO/Rh groups are fixed by biology, not configurable.
            $table->enum('blood_type', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])->nullable();
            // Self-reference for child health tracking. SET NULL: a child's record
            // survives if the mother's record is permanently deleted.
            $table->foreignId('mother_id')->nullable()->constrained('residents')->cascadeOnUpdate()->nullOnDelete();
            $table->enum('relationship_to_head', [
                'head', 'spouse', 'child', 'parent', 'sibling',
                'grandchild', 'grandparent', 'in_law', 'other_relative', 'non_relative',
            ]);
            $table->enum('status', ['active', 'moved', 'deceased'])->default('active');
            $table->string('archive_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Name search (prefix LIKE) + duplicate detection (last, first, birthdate).
            $table->index(['last_name', 'first_name', 'birthdate'], 'residents_name_birthdate_index');
            $table->index('first_name');
            // Birthdate search and age-bracket reports (seniors, children).
            $table->index('birthdate');
            // Every report filters "active and not archived" then by age:
            // equality on status + deleted_at, range on birthdate — one covering index.
            $table->index(['status', 'deleted_at', 'birthdate'], 'residents_status_deleted_birthdate_index');
        });

        // Word search anywhere in the name ("cruz" finds "Dela Cruz").
        MysqlSchema::fullText('residents', 'residents_name_fulltext', ['last_name', 'first_name', 'middle_name']);

        Schema::table('households', function (Blueprint $table) {
            // SET NULL: if the head's record is permanently deleted, the household survives without a head.
            $table->foreign('head_resident_id')->references('id')->on('residents')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropForeign(['head_resident_id']);
        });

        Schema::dropIfExists('residents');
    }
};
