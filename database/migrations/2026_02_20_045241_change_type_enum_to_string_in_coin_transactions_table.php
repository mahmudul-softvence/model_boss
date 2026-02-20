<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE coin_transactions
            MODIFY COLUMN `type` VARCHAR(50) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE coin_transactions
            MODIFY COLUMN `type` ENUM(
                'recharge',
                'support',
                'win',
                'loss',
                'withdraw'
            ) NOT NULL
        ");
    }
};
