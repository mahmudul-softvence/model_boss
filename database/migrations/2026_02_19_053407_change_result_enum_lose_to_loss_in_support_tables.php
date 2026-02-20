<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE final_supports
            MODIFY COLUMN result
            ENUM('pending', 'win', 'loss')
            DEFAULT 'pending'
        ");

        DB::statement("
            ALTER TABLE supports
            MODIFY COLUMN result
            ENUM('pending', 'win', 'loss')
            DEFAULT 'pending'
        ");
    }

    public function down(): void
    {

        DB::statement("
            ALTER TABLE final_supports
            MODIFY COLUMN result
            ENUM('pending', 'win', 'lose')
            DEFAULT 'pending'
        ");

        DB::statement("
            ALTER TABLE supports
            MODIFY COLUMN result
            ENUM('pending', 'win', 'lose')
            DEFAULT 'pending'
        ");
    }
};
