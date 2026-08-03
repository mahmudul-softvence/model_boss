<?php

namespace Database\Seeders;

use App\Models\PromotionalTerm;
use Illuminate\Database\Seeder;

class PromotionalTermSeeder extends Seeder
{
    public function run(): void
    {
        if (PromotionalTerm::query()->exists()) {
            return;
        }

        PromotionalTerm::replaceContent(1000, [
            'This promotional offer is available for a limited time only.',
            'Each user is eligible for this promotional price offer only once.',
            'Promotional balances may have wagering or usage requirements before withdrawal.',
            'Model Boss may modify or end this promotion at any time.',
            'Abuse, duplicate accounts, or fraudulent activity will void promotional rewards.',
        ]);
    }
}
