<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->text('description');
            $table->boolean('is_completed')->default(false);
            $table->unsignedBigInteger('added_by')->nullable();
            $table->unsignedBigInteger('assign_to');
            $table->date('remainder_at')->nullable();
            $table->boolean('is_viewed')->default(false);
            $table->timestamps();

            // Foreign keys
            $table->foreign('added_by')->references('user_id')->on('user_details')->onDelete('set null');
            $table->foreign('assign_to')->references('user_id')->on('user_details')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('reminders');
    }
};
