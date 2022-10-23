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
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->nullable()->unique();
            $table->string('device_model')->nullable();
            $table->string('device_os')->nullable();
            $table->string('device_os_version')->nullable();
            $table->string('device_name')->nullable();
            $table->string('notification_token')->unique();
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
        Schema::dropIfExists('user_devices');
    }
};
