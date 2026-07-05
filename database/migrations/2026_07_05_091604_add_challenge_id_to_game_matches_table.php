<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->foreignId('challenge_id')
                ->nullable()
                ->constrained('challenges')
                ->nullOnDelete()
                ->after('match_type');
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropForeign(['challenge_id']);
            $table->dropColumn('challenge_id');
        });
    }
};
