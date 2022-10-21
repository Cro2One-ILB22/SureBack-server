<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToUserDeviceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_device', function (Blueprint $table) {
            $table->foreign('refresh_token_id')->references(['id'])->on('refresh_token')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('user_notification_id')->references(['id'])->on('user_notification')->onUpdate('RESTRICT')->onDelete('RESTRICT');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_device', function (Blueprint $table) {
            $table->dropForeign('user_device_refresh_token_id_foreign');
            $table->dropForeign('user_device_user_notification_id_foreign');
        });
    }
}
