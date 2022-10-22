<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToCustomerStoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_story', function (Blueprint $table) {
            $table->foreign('customer_id')->references(['id'])->on('user')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('story_token_id')->references(['id'])->on('story_token')->onUpdate('RESTRICT')->onDelete('RESTRICT');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_story', function (Blueprint $table) {
            $table->dropForeign('customer_story_customer_id_foreign');
            $table->dropForeign('customer_story_story_token_id_foreign');
        });
    }
}
