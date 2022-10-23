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
        Schema::table('notification_subscriptions', function (Blueprint $table) {
            $table->foreign('notification_topic_id')->references(['id'])->on('notification_topics')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('user_id')->references(['id'])->on('users')->onUpdate('RESTRICT')->onDelete('RESTRICT');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('notification_subscriptions', function (Blueprint $table) {
            $table->dropForeign('notification_subscriptions_notification_topic_id_foreign');
            $table->dropForeign('notification_subscriptions_user_id_foreign');
        });
    }
};

