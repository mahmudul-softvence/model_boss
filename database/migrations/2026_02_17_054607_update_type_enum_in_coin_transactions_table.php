<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE coin_transactions
            MODIFY COLUMN type ENUM(
                'recharge',
                'support',
                'win',
                'loss',
                'withdraw',
                'support-return'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE coin_transactions
            MODIFY COLUMN type ENUM(
                'recharge',
                'support',
                'win',
                'loss',
                'withdraw'
            ) NOT NULL
        ");
    }
};
