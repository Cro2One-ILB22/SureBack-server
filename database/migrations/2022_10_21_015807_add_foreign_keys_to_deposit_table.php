<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToDepositTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('deposit', function (Blueprint $table) {
            $table->foreign('corporate_account_id')->references(['id'])->on('corporate_account')->onUpdate('RESTRICT')->onDelete('RESTRICT');
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
        Schema::table('deposit', function (Blueprint $table) {
            $table->dropForeign('deposit_corporate_account_id_foreign');
            $table->dropForeign('deposit_financial_transaction_id_foreign');
        });
    }
}
