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
        Schema::create('coin_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_transaction_id')->constrained('financial_transactions')->restrictOnDelete();
            $table->foreignId('merchant_transaction_id')->constrained('financial_transactions')->restrictOnDelete();
            $table->enum('coin_type', CoinTypeEnum::values());
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
        Schema::dropIfExists('coin_exchanges');
    }
};
