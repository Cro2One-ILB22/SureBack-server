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
        Schema::create('corporate_instagrams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('instagram_id')->nullable();
            $table->string('name')->nullable();
            $table->string('username');
            $table->string('session_id')->nullable();
            $table->string('csrf_token')->nullable();
            $table->boolean('is_active')->default(false);
            $table->bigInteger('used_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
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
        Schema::dropIfExists('corporate_instagrams');
    }
};
