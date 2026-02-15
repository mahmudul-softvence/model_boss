<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->decimal('player_one_bet', 10, 2)
                  ->default(0)
                  ->after('player_one_id');

            $table->decimal('player_two_bet', 10, 2)
                  ->default(0)
                  ->after('player_two_id');
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn(['player_one_bet', 'player_two_bet']);
        });
    }
};

