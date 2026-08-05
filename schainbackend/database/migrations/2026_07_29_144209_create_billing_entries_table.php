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
        Schema::create('billing_entries', function (Blueprint $table) {
            $table->id('bill_id');
            $table->foreignId('over_all_bill_id')->nullable()->constrained('over_all_bills')->onDelete('cascade');
            $table->string('type', 45);
            $table->unsignedBigInteger('head_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('ob_purity', 12, 4);
            $table->decimal('ob_grams', 12, 4);
            $table->decimal('cb_purity', 12, 4)->nullable();
            $table->decimal('cb_grams', 12, 4)->nullable();
            $table->decimal('from_ob_purity', 12, 4)->default(0);
            $table->decimal('from_ob_grams', 12, 4)->default(0);
            $table->decimal('from_cb_purity', 12, 4)->default(0);
            $table->decimal('from_cb_grams', 12, 4)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamp('added_at')->useCurrent();
            $table->timestamps();

            $table->foreign('head_id')->references('user_id')->on('user_details')->onDelete('cascade');
            $table->foreign('user_id')->references('user_id')->on('user_details')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_entries');
    }
};
