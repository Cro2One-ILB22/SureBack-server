<?php

use App\Enums\InstagramStoryStatusEnum;
use App\Enums\StoryApprovalStatusEnum;
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
        Schema::create('customer_stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id');
            $table->foreignId('story_token_id');
            $table->unsignedBigInteger('instagram_story_id')->nullable()->unique();
            $table->unsignedBigInteger('instagram_id');
            $table->text('image_uri')->nullable();
            $table->text('video_uri')->nullable();
            $table->enum('instagram_story_status', InstagramStoryStatusEnum::values())->nullable();
            $table->enum('approval_status', StoryApprovalStatusEnum::values())->nullable();
            $table->timestamp('submitted_at')->nullable();
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
        Schema::dropIfExists('customer_stories');
    }
};
