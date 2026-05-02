<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->tinyInteger('pin_to_top')->default(0)->after('rules');
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn('pin_to_top');
        });
    }
};
