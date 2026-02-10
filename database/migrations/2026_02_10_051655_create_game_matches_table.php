<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_matches', function (Blueprint $table) {
            $table->id();

            $table->string('match_no')->unique();

            $table->unsignedBigInteger('player_one_id');
            $table->unsignedBigInteger('player_two_id');

            $table->string('game_id');

            $table->unsignedBigInteger('winner_id')->nullable();

            // enum type
            $table->enum('type', ['upcoming', 'live', 'completed'])
                  ->default('upcoming');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_matches');
    }
};
