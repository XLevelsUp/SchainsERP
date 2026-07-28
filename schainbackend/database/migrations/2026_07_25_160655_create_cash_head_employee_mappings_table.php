<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_head_employee_mappings', function (Blueprint $table) {

            $table->bigIncrements('id');

            $table->unsignedBigInteger('head_id');

            $table->unsignedBigInteger('employee_id');

            $table->timestamp('added_at')
                ->useCurrent();

            $table->foreign('head_id')
                ->references('user_id')
                ->on('user_details')
                ->onDelete('cascade');

            $table->foreign('employee_id')
                ->references('user_id')
                ->on('user_details')
                ->onDelete('cascade');

            $table->index(
                'head_id',
                'cash_head_employee_mappings_head_id_idx'
            );

            $table->index(
                'employee_id',
                'cash_head_employee_mappings_employee_id_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_head_employee_mappings');
    }
};