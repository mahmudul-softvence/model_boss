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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('reference_status')->default(false);
            $table->foreignId('referral_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('referral_no', 50)->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'reference_status',
                'referral_user_id',
                'referral_no',
            ]);
        });
    }
};
