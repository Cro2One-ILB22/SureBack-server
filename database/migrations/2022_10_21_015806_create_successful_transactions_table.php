<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('successful_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_transaction_id');
            $table->bigInteger('balance_before');
            $table->bigInteger('balance_after');
            $table->bigInteger('points_before');
            $table->bigInteger('points_after');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('successful_transactions');
    }
};