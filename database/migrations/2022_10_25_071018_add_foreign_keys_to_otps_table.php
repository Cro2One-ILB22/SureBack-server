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
        Schema::table('otps', function (Blueprint $table) {
            $table->foreign('user_id')->references(['id'])->on('users')->restrictOnUpdate()->restrictOnDelete();
            $table->foreign('factor_id')->references('id')->on('otp_factors')->restrictOnUpdate()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->dropForeign('otps_user_id_foreign');
            $table->dropForeign('otps_factor_id_foreign');
        });
    }
};
