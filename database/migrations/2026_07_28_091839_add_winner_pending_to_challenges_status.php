<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE challenges MODIFY COLUMN status ENUM(
                'pending','offered','accepted','under_review','rejected','declined',
                'cancelled','expired','winner_pending','completed'
            ) NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE challenges MODIFY COLUMN status ENUM(
                'pending','offered','accepted','under_review','rejected','declined',
                'cancelled','expired','completed'
            ) NOT NULL DEFAULT 'pending'");
        }
    }
};
