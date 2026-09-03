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
        Schema::create('customer_touch_user_mappings', function (Blueprint $table) {
            $table->id();
            
            // Link to user_details table
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')
                  ->references('user_id')
                  ->on('user_details')
                  ->onDelete('cascade');

            // Link to customer_touch table
            $table->unsignedBigInteger('customer_touch_id');
            $table->foreign('customer_touch_id')
                  ->references('item_id')
                  ->on('customer_touch')
                  ->onDelete('cascade');

            $table->boolean('is_active')->default(true);
            
            $table->timestamp('added_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            
            // A user should only be mapped to a specific customer touch once
            $table->unique(['user_id', 'customer_touch_id'], 'idx_unique_user_customer_touch');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_touch_user_mappings');
    }
};
