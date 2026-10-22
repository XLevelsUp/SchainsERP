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
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50); // 'STOCK', 'CASH'
            $table->string('action', 50); // 'STOCK_OUT', 'STOCK_IN', 'CASH_IN', 'CASH_OUT', etc.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_table', 50)->nullable();
            $table->text('description')->nullable();
            $table->jsonb('payload')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Indexes for fast auditing and dashboard filtering
            $table->index(['module', 'action'], 'idx_system_logs_module_action');
            $table->index(['user_id'], 'idx_system_logs_user_id');
            $table->index(['reference_table', 'reference_id'], 'idx_system_logs_ref');
            $table->index(['created_at'], 'idx_system_logs_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
