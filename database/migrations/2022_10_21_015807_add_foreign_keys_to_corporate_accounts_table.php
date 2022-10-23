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
        Schema::table('corporate_accounts', function (Blueprint $table) {
            $table->foreign('bank_id')->references(['id'])->on('banks')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('payment_method_id')->references(['id'])->on('payment_methods')->onUpdate('RESTRICT')->onDelete('RESTRICT');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('corporate_accounts', function (Blueprint $table) {
            $table->dropForeign('corporate_accounts_bank_id_foreign');
            $table->dropForeign('corporate_accounts_payment_method_id_foreign');
        });
    }
};
