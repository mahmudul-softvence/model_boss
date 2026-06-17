<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();

            $table->string('challenge_no')->unique();

            // Parties
            $table->unsignedBigInteger('challenger_id');
            $table->enum('mode', ['unique', 'global']);
            $table->unsignedBigInteger('target_player_id')->nullable();
            $table->unsignedBigInteger('accepted_by_user_id')->nullable();
            $table->timestamp('accepted_at')->nullable();

            // Offer
            $table->string('game_id');
            $table->decimal('amount', 12, 2);
            $table->string('logo')->nullable();
            $table->text('memo')->nullable();
            $table->boolean('show_real_name')->default(true);
            $table->date('match_date')->nullable();
            $table->string('match_time')->nullable();

            // Lifecycle
            $table->enum('status', [
                'pending', 'offered', 'accepted', 'rejected',
                'declined', 'cancelled', 'expired', 'completed',
            ])->default('pending');
            $table->unsignedInteger('duration_hours')->default(24);
            $table->timestamp('offer_expires_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('winner_id')->nullable();
            $table->timestamp('settled_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('challenger_id');
            $table->index(['status', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
