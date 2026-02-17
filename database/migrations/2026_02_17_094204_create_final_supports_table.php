<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('final_supports', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('match_id')->index();
            $table->string('match_no')->index();

            $table->unsignedBigInteger('supported_player_id')->index();
            $table->unsignedBigInteger('user_id')->index();

            $table->decimal('coin_amount', 12, 2);

            $table->enum('result', ['pending', 'win', 'lose'])->default('pending');

            $table->timestamps();

            $table->foreign('match_id')->references('id')->on('game_matches')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('final_supports');
    }
};
