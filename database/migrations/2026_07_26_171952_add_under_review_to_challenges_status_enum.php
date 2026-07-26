<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE challenges MODIFY COLUMN status ENUM(
                'pending', 'offered', 'accepted', 'under_review', 'rejected',
                'declined', 'cancelled', 'expired', 'completed'
            ) NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE challenges MODIFY COLUMN status ENUM(
                'pending', 'offered', 'accepted', 'rejected',
                'declined', 'cancelled', 'expired', 'completed'
            ) NOT NULL DEFAULT 'pending'");
        }
    }
};
