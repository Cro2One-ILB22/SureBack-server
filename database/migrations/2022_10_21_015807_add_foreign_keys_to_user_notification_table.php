<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToUserNotificationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_notification', function (Blueprint $table) {
            $table->foreign('notification_topic_id')->references(['id'])->on('notification_topic')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('user_id')->references(['id'])->on('user')->onUpdate('RESTRICT')->onDelete('RESTRICT');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_notification', function (Blueprint $table) {
            $table->dropForeign('user_notification_notification_topic_id_foreign');
            $table->dropForeign('user_notification_user_id_foreign');
        });
    }
}
