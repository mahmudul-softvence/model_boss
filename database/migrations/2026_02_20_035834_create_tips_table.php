<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tips', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('send_user_id')->index();
            $table->unsignedBigInteger('received_user_id')->index();

            $table->decimal('tip_amount', 12, 2);

            $table->timestamps();

            // Foreign Keys
            $table->foreign('send_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('received_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tips');
    }
};
