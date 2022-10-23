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
        Schema::table('customer_stories', function (Blueprint $table) {
            $table->foreign('customer_id')->references(['id'])->on('users')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('story_token_id')->references(['id'])->on('story_tokens')->onUpdate('RESTRICT')->onDelete('RESTRICT');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_stories', function (Blueprint $table) {
            $table->dropForeign('customer_stories_customer_id_foreign');
            $table->dropForeign('customer_stories_story_token_id_foreign');
        });
    }
};
