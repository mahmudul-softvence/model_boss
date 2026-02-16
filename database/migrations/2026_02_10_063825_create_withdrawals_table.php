<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');

            $table->string('withdraw_no')->unique();

            $table->decimal('coin_amount', 12, 2);
            $table->decimal('usd_amount', 12, 2);
            $table->string('stripe_transfer_id')->nullable();

            $table->enum('status', ['pending', 'accepted', 'declined', 'paid'])->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
