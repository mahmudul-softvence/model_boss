<?php

namespace Database\Seeders;

use App\Models\TermsAndCondition;
use Illuminate\Database\Seeder;

class TermsAndConditionSeeder extends Seeder
{
    public function run(): void
    {
        if (TermsAndCondition::query()->exists()) {
            return;
        }

        TermsAndCondition::replaceContent(
            'Terms and Conditions',
            <<<'TEXT'
These Terms and Conditions govern your access to and use of Model Boss Offers.

1. Acceptance of Terms
By creating an account or using the platform, you agree to these Terms and Conditions and our Privacy Policy.

2. Eligibility
You must provide accurate account information and be legally allowed to use the platform in your jurisdiction.

3. Accounts and Conduct
You are responsible for your account activity. Harassment, cheating, fraud, account sharing, or abuse of promotions may result in suspension or termination.

4. Matches, Challenges, and Tips
All matches, challenges, tips, and related balances are subject to platform rules. Disputes may be reviewed by administrators, and decisions may be final.

5. Payments and Withdrawals
Payments and withdrawals are processed through supported providers and may be subject to verification, fees, limits, and processing times.

6. Intellectual Property
Platform content, branding, and materials remain the property of Model Boss Offers and may not be used without permission.

7. Limitation of Liability
The platform is provided as available. To the fullest extent permitted by law, Model Boss Offers is not liable for indirect or consequential losses arising from use of the service.

8. Changes
We may update these Terms and Conditions at any time. Continued use of the platform after changes means you accept the updated terms.

If you do not agree with these Terms and Conditions, you should stop using the platform.
TEXT
        );
    }
}
