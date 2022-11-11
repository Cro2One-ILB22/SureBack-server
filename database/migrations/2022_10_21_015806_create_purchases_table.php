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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('merchant_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_transaction_id')->constrained('transactions')->restrictOnDelete();
            $table->foreignId('merchant_transaction_id')->constrained('transactions')->restrictOnDelete();
            $table->bigInteger('purchase_amount');
            $table->bigInteger('payment_amount');
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
        Schema::dropIfExists('purchases');
    }
};
