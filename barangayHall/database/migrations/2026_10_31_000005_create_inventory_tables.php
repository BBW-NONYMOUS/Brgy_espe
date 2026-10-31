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
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('generic_name', 120);
            $table->string('brand_name', 120)->nullable();
            $table->enum('form', ['tablet', 'capsule', 'syrup', 'suspension', 'drops', 'injection', 'sachet', 'ointment', 'inhaler', 'other']);
            $table->string('strength', 40); // "500 mg", "250 mg/5 mL"
            $table->string('unit', 20); // unit of issue: tablet, bottle, sachet, vial
            // Low-stock alert when the non-expired total falls to or below this.
            $table->unsignedInteger('reorder_level')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['generic_name', 'form', 'strength']);
        });

        Schema::create('medicine_batches', function (Blueprint $table) {
            $table->id();
            // RESTRICT: stock history must never vanish with a medicine.
            $table->foreignId('medicine_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('batch_no', 40);
            $table->date('expiry_date');
            $table->unsignedInteger('quantity_received');
            // Stored on purpose (see data dictionary): changed only by InventoryService
            // inside the same transaction that inserts the stock_movements row, with the
            // batch row locked. SUM(stock_movements.quantity) must always equal it.
            $table->unsignedInteger('quantity_on_hand');
            $table->date('received_at');
            $table->string('source', 80)->nullable(); // DOH, LGU, donation
            // Last expiry alert band sent (90, 60, 30, 0 = expired) so each band notifies once.
            $table->unsignedSmallInteger('expiry_alert_band')->nullable();
            $table->foreignId('received_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['medicine_id', 'batch_no']);
            // FEFO pick order and per-medicine "nearest expiry".
            $table->index(['medicine_id', 'expiry_date']);
            // Daily expiry sweep across all medicines.
            $table->index('expiry_date');
        });

        // Append-only ledger. Signed quantity: + in / adjustment up, − dispensed /
        // expired write-off / out / adjustment down.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_batch_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->enum('type', ['in', 'out', 'dispensed', 'expired', 'adjustment']);
            $table->integer('quantity');
            $table->foreignId('resident_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('health_visit_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['medicine_batch_id', 'created_at']);
            // Dispensing history on the resident profile.
            $table->index(['resident_id', 'created_at']);
            // Stock report: movements by type within a date range.
            $table->index(['type', 'created_at']);
        });

        MysqlSchema::check('medicine_batches', 'medicine_batches_quantity_check', 'quantity_on_hand >= 0 AND quantity_received > 0');
        MysqlSchema::check('medicine_batches', 'medicine_batches_dates_check', 'expiry_date > received_at');
        MysqlSchema::check('stock_movements', 'stock_movements_quantity_check', 'quantity <> 0');
        MysqlSchema::check('stock_movements', 'stock_movements_sign_check', "(type = 'in' AND quantity > 0) OR (type IN ('out', 'dispensed', 'expired') AND quantity < 0) OR type = 'adjustment'");
        // "Dispensed rows need a resident" is enforced by InventoryService, not a CHECK:
        // resident_id is SET NULL on permanent deletion (retention), which a CHECK would block.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('medicine_batches');
        Schema::dropIfExists('medicines');
    }
};
