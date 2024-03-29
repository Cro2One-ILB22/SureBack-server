<?php

use App\Enums\CoinTypeEnum;
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
        Schema::create('user_coins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('merchant_id')->constrained('users')->restrictOnDelete();
            $table->bigInteger('all_time_reward')->default(0);
            $table->bigInteger('outstanding')->default(0);
            $table->bigInteger('exchanged')->default(0);
            $table->enum('coin_type', CoinTypeEnum::values())->default(CoinTypeEnum::LOCAL->value);
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
        Schema::dropIfExists('user_coins');
    }
};
