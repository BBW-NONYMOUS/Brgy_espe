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
        // Append-only. No updated_at: rows are never modified.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Nullable: failed logins and scheduled jobs have no user.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('action', 30);
            $table->string('table_name', 64)->nullable();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->string('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // "History of this record" on profile pages.
            $table->index(['table_name', 'record_id']);
            // Audit log screen filters.
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('file_name')->unique();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->enum('type', ['scheduled', 'manual']);
            // "pruned": file removed by the retention rotation; the row stays as history.
            $table->enum('status', ['running', 'success', 'failed', 'pruned'])->default('running');
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backups');
        Schema::dropIfExists('audit_logs');
    }
};
