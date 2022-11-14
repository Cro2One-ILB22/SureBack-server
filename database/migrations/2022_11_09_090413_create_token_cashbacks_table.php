<?php

use App\Enums\CashbackCalculationMethodEnum;
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
        Schema::create('token_cashbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('token_id')->constrained('story_tokens')->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->float('percent')->nullable();
            $table->enum('coin_type', CoinTypeEnum::values());
            $table->enum('cashback_calculation_method', CashbackCalculationMethodEnum::values());
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
        Schema::dropIfExists('token_cashbacks');
    }
};
