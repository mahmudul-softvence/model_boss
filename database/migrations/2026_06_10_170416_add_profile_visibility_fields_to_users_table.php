<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('show_name')->default(true)->after('show_email');
            $table->boolean('show_total_earning')->default(true)->after('show_name');
            $table->boolean('show_total_referral_earning')->default(true)->after('show_total_earning');
            $table->boolean('show_total_tip_received')->default(true)->after('show_total_referral_earning');
            $table->boolean('show_total_withdraw')->default(true)->after('show_total_tip_received');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'show_name',
                'show_total_earning',
                'show_total_referral_earning',
                'show_total_tip_received',
                'show_total_withdraw',
            ]);
        });
    }
};
