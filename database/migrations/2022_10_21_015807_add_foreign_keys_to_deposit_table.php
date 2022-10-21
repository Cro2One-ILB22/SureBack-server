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
            $table->foreign('user_id')->references(['id'])->on('refresh_token')->onUpdate('RESTRICT')->onDelete('RESTRICT');
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
            $table->dropForeign('deposit_user_id_foreign');
        });
    }
}
