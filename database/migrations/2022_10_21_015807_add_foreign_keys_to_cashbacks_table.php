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
        Schema::table('cashbacks', function (Blueprint $table) {
            $table->foreign('story_id')->references(['id'])->on('customer_stories')->onUpdate('RESTRICT')->onDelete('RESTRICT');
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
        Schema::table('cashbacks', function (Blueprint $table) {
            $table->dropForeign('cashbacks_story_id_foreign');
            $table->dropForeign('cashbacks_financial_transaction_id_foreign');
        });
    }
};
