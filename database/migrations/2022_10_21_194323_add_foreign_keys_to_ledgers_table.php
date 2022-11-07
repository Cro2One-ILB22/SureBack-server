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
        Schema::table('ledgers', function (Blueprint $table) {
            $table->foreign('financial_transaction_id')->references('id')->on('financial_transactions')->restrictOnDelete();
            $table->foreign('payment_instrument_id')->references('id')->on('payment_instruments')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ledgers', function (Blueprint $table) {
            $table->dropForeign('ledgers_financial_transaction_id_foreign');
            $table->dropForeign('ledgers_payment_instrument_id_foreign');
        });
    }
};
