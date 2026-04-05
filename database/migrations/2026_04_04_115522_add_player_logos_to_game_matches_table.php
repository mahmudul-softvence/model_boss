<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->string('player_one_logo')->nullable()->after('player_one_id');
            $table->string('player_two_logo')->nullable()->after('player_two_id');
        });
    }

    public function down()
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn(['player_one_logo', 'player_two_logo']);
        });
    }
};
