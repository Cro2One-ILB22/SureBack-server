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
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->foreign('user_id')->references(['id'])->on('users')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('transaction_category_id')->references(['id'])->on('transaction_categories')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('transaction_status_id')->references(['id'])->on('transaction_statuses')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('payment_instrument_id')->references(['id'])->on('payment_instruments')->onUpdate('RESTRICT')->onDelete('RESTRICT');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropForeign('financial_transactions_user_id_foreign');
            $table->dropForeign('financial_transactions_transaction_category_id_foreign');
            $table->dropForeign('financial_transactions_transaction_status_id_foreign');
            $table->dropForeign('financial_transactions_payment_instrument_id_foreign');
        });
    }
};
