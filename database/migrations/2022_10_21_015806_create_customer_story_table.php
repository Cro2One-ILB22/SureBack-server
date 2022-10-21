<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerStoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_story', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id');
            $table->foreignId('partner_id');
            $table->string('story_token');
            $table->string('instagram_story_id');
            $table->string('image_uri');
            $table->enum('status', config('enums.story_status'))->default(config('enums.story_status.pending'));
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
        Schema::dropIfExists('customer_story');
    }
}
