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
        Schema::table('story_tokens', function (Blueprint $table) {
            $table->foreign('purchase_id')->references(['id'])->on('purchases')->restrictOnUpdate()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('story_tokens', function (Blueprint $table) {
            $table->dropForeign('story_tokens_purchase_id_foreign');
        });
    }
};
