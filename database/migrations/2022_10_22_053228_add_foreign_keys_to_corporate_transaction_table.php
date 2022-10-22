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
        Schema::table('corporate_transaction', function (Blueprint $table) {
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
        Schema::table('corporate_transaction', function (Blueprint $table) {
            $table->dropForeign('corporate_transaction_financial_transaction_id_foreign');
        });
    }
};
