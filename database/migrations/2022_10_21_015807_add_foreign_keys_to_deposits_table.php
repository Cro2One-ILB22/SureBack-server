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
        Schema::table('deposits', function (Blueprint $table) {
            $table->foreign('corporate_account_id')->references(['id'])->on('corporate_accounts')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('transaction_id')->references(['id'])->on('transactions')->onUpdate('RESTRICT')->onDelete('RESTRICT');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropForeign('deposits_corporate_account_id_foreign');
            $table->dropForeign('deposits_transaction_id_foreign');
        });
    }
};
