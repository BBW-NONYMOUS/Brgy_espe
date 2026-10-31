<?php

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
        // Laravel's default database-notification table. `data` holds counts, IDs
        // and links only — never names or diagnoses (it is not encrypted).
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type', 120);
            $table->unsignedBigInteger('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Bell: unread count / latest for one user.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });

        // One row per digest/alert sent ("overdue-care:2026-10-31", "outbreak:12:count:4").
        // Lets the daily job re-run safely without notifying twice.
        Schema::create('notification_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('dedupe_key', 120)->unique();
            $table->string('notification_type', 120);
            $table->unsignedInteger('recipients_count');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_dispatches');
        Schema::dropIfExists('notifications');
    }
};
