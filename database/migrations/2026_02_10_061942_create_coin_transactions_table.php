<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coin_transactions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');

            $table->enum('type', [
                'recharge',
                'support',
                'win',
                'loss',
                'withdraw',
            ]);

            $table->decimal('amount', 12, 2);        // number of coins changed
            $table->decimal('balance_after', 12, 2); // coin balance after this transaction

            $table->string('reference')->nullable(); // stripe session id, match id, etc

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_transactions');
    }
};
