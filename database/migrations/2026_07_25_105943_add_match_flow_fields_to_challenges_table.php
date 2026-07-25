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
        Schema::table('challenges', function (Blueprint $table) {
            $table->timestamp('challenger_ready_at')->nullable()->after('accepted_at');
            $table->timestamp('acceptor_ready_at')->nullable()->after('challenger_ready_at');
            $table->timestamp('started_at')->nullable()->after('acceptor_ready_at');
            $table->timestamp('submitted_for_review_at')->nullable()->after('started_at');
            $table->timestamp('admin_reviewed_at')->nullable()->after('submitted_for_review_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->dropColumn([
                'challenger_ready_at',
                'acceptor_ready_at',
                'started_at',
                'submitted_for_review_at',
                'admin_reviewed_at',
            ]);
        });
    }
};
