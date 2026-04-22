<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('bitpay_payments');
    }

    public function down(): void
    {
        Schema::create('bitpay_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('order_id')->unique();
            $table->string('bitpay_invoice_id')->nullable()->unique();
            $table->decimal('amount', 12, 2);
            $table->decimal('coin_amount', 12, 2);
            $table->string('payer')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }
};
