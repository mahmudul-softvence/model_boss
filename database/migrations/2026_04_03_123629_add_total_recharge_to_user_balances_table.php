<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('user_balances', function (Blueprint $table) {
            $table->decimal('total_recharge', 15, 2)->default(0)->after('total_bet');
        });
    }

    public function down()
    {
        Schema::table('user_balances', function (Blueprint $table) {
            $table->dropColumn('total_recharge');
        });
    }
};
