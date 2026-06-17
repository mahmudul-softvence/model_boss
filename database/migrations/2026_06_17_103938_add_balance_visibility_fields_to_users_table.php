<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('show_total_balance')->default(true)->after('show_total_withdraw');
            $table->boolean('show_total_bet')->default(true)->after('show_total_balance');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'show_total_balance',
                'show_total_bet',
            ]);
        });
    }
};
