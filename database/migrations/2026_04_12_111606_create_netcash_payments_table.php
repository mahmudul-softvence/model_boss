<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('netcash_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('reference')->unique();
            $table->string('request_trace')->nullable()->unique();
            $table->decimal('amount', 12, 2);
            $table->decimal('coin_amount', 12, 2);
            $table->string('payment_method')->nullable();
            $table->string('reason')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('netcash_payments');
    }
};
