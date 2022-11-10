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
        Schema::create('notification_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->nullableMorphs('notification_subscriptionable', 'notification_subscriptionable_index');
            $table->string('slug');
            $table->string('name');
            $table->boolean('active')->default(true);
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
        Schema::dropIfExists('notification_subscriptions');
    }
};
