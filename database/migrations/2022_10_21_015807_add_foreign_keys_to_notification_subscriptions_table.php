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
            $table->foreign('user_id')->references(['id'])->on('users')->restrictOnUpdate()->restrictOnDelete();
            $table->foreign('notification_topic_id')->references(['id'])->on('notification_topics')->restrictOnDelete();
            $table->foreign('notification_group_id')->references(['id'])->on('notification_groups')->restrictOnDelete();
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
            $table->dropForeign('notification_subscriptions_user_id_foreign');
            $table->dropForeign('notification_subscriptions_notification_topic_id_foreign');
            $table->dropForeign('notification_subscriptions_notification_group_id_foreign');
        });
    }
};
