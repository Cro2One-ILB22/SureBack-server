<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToCorporateAccountTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('corporate_account', function (Blueprint $table) {
            $table->foreign('bank_id')->references(['id'])->on('bank')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('payment_method_id')->references(['id'])->on('payment_method')->onUpdate('RESTRICT')->onDelete('RESTRICT');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('corporate_account', function (Blueprint $table) {
            $table->dropForeign('corporate_account_bank_id_foreign');
            $table->dropForeign('corporate_account_payment_method_id_foreign');
        });
    }
}
