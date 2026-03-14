<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('check_live_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('platform_name');
            $table->enum('platform_live_status', ['live', 'pause', 'stop'])->default('stop');
            $table->enum('mode', ['landscape', 'portrait'])->default('landscape');
            $table->timestamp('live_started_at')->nullable();
            $table->timestamp('live_stopped_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_live_statuses');
    }
};
