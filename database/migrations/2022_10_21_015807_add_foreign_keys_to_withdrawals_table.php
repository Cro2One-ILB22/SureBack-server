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
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->foreign('bank_account_id')->references(['id'])->on('bank_accounts')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('financial_transaction_id')->references(['id'])->on('financial_transactions')->onUpdate('RESTRICT')->onDelete('RESTRICT');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropForeign('withdrawals_bank_account_id_foreign');
            $table->dropForeign('withdrawals_financial_transaction_id_foreign');
        });
    }
};
