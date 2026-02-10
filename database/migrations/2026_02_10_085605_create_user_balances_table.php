<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('user_balances', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->unique();

            $table->decimal('total_earning', 12, 2)->default(0);
            $table->decimal('total_referral_earning', 12, 2)->default(0);
            $table->decimal('total_tip_received', 12, 2)->default(0);
            $table->decimal('total_withdraw', 12, 2)->default(0);
            $table->decimal('total_balance', 12, 2)->default(0);

            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('user_balances');
    }
};
