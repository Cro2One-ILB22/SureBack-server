<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCashbackTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cashback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id');
            $table->integer('amount')->default(0);
            $table->enum('status', config('enums.cashback_status'))->default(config('enums.cashback_status.pending'));
            $table->string('note')->nullable();
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
        Schema::dropIfExists('cashback');
    }
}
