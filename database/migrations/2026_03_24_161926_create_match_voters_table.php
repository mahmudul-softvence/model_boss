<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_voters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('match_for_voting_id');
            $table->timestamps();

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->cascadeOnDelete();

            $table->foreign('match_for_voting_id')
                  ->references('id')->on('match_for_votings')
                  ->cascadeOnDelete();

            $table->unique(['user_id', 'match_for_voting_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_voters');
    }
};
