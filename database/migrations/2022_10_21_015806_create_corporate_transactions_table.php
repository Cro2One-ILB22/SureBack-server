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
        Schema::create('corporate_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_transaction_id')->nullable();
            $table->bigInteger('amount');
            $table->bigInteger('balance_before');
            $table->bigInteger('balance_after');
            $table->enum('type', config('enums.transaction_type'));
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
        Schema::dropIfExists('corporate_transactions');
    }
};
