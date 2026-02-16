<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->decimal('player_one_total', 12, 2)
                ->after('player_one_bet')
                ->default(0);

            $table->decimal('player_two_total', 12, 2)
                ->after('player_two_bet')
                ->default(0);
            $table->string('tiktok_link',500)->nullable()->after('loser_percentage');
            $table->string('twitch_link',500)->nullable()->after('tiktok_link');
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn([
                'player_one_total',
                'player_two_total',
                'tiktok_link',
                'twitch_link',
            ]);
        });
    }
};

