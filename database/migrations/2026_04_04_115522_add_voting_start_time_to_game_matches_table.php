<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dateTime('vote_start_time')->nullable()->after('match_time');
            $table->dateTime('voting_time')->nullable()->after('vote_start_time');
        });
    }

    public function down()
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn([
                'vote_start_time',
                'voting_time',
            ]);
        });
    }
};
