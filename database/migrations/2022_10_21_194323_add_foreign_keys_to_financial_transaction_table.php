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
        Schema::table('financial_transaction', function (Blueprint $table) {
            $table->foreign('user_id')->references(['id'])->on('user')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('transaction_category_id')->references(['id'])->on('transaction_category')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('transaction_status_id')->references(['id'])->on('transaction_status')->onUpdate('RESTRICT')->onDelete('RESTRICT');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('financial_transaction', function (Blueprint $table) {
            $table->dropForeign('financial_transaction_user_id_foreign');
            $table->dropForeign('financial_transaction_transaction_category_id_foreign');
            $table->dropForeign('financial_transaction_transaction_status_id_foreign');
        });
    }
};
