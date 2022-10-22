<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToStoryTransactionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('story_transaction', function (Blueprint $table) {
            $table->foreign('story_token_id')->references('id')->on('story_token')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('transaction_id')->references('id')->on('financial_transaction')->onUpdate('RESTRICT')->onDelete('RESTRICT');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('story_transaction', function (Blueprint $table) {
            $table->dropForeign('story_transaction_story_token_id_foreign');
            $table->dropForeign('story_transaction_transaction_id_foreign');
        });
    }
}
