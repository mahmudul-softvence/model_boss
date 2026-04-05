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
        Schema::create('player_votes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('voted_player_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('match_id')
                ->constrained('game_matches')
                ->cascadeOnDelete();

            $table->integer('total_vote')->default(0);

            $table->timestamps();

            $table->index(['match_id', 'voted_player_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_votes');
    }
};
