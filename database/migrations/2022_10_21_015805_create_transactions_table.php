<?php

use App\Enums\AccountingEntryEnum;
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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->foreignId('transaction_category_id');
            $table->foreignId('transaction_status_id');
            $table->foreignId('payment_instrument_id');
            $table->bigInteger('amount');
            $table->string('description')->nullable();
            $table->enum('accounting_entry', AccountingEntryEnum::values());
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
        Schema::dropIfExists('transactions');
    }
};
