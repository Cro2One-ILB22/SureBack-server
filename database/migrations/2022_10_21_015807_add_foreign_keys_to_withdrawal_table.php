<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToWithdrawalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('withdrawal', function (Blueprint $table) {
            $table->foreign('bank_account_id')->references(['id'])->on('bank_account')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('financial_transaction_id')->references(['id'])->on('financial_transaction')->onUpdate('RESTRICT')->onDelete('RESTRICT');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('withdrawal', function (Blueprint $table) {
            $table->dropForeign('withdrawal_bank_account_id_foreign');
            $table->dropForeign('withdrawal_financial_transaction_id_foreign');
        });
    }
}
