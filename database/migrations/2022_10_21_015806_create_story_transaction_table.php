<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStoryTransactionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('story_transaction', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id');
            $table->integer('amount');
            $table->integer('balance_change');
            $table->integer('point_change');
            $table->enum('type', config('enums.story_transaction'));
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('story_transaction');
    }
}
