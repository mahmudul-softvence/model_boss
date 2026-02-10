<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('supports', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('match_id');
            $table->string('match_no');

            $table->unsignedBigInteger('supported_player_id');
            $table->unsignedBigInteger('user_id'); // supporter (user)

            $table->decimal('coin_amount', 12, 2);

            $table->enum('result', ['pending', 'win', 'lose'])->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supports');
    }
};
