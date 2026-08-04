<?php

namespace Database\Seeders;

use App\Models\PrivacyPolicy;
use Illuminate\Database\Seeder;

class PrivacyPolicySeeder extends Seeder
{
    public function run(): void
    {
        if (PrivacyPolicy::query()->exists()) {
            return;
        }

        PrivacyPolicy::replaceContent(
            'Privacy Policy',
            <<<'TEXT'
This Privacy Policy explains how Model Boss Offers collects, uses, and protects your personal information when you use our platform.

1. Information We Collect
We may collect account details such as your name, email address, profile information, payment and withdrawal details, and activity related to matches, challenges, and tips.

2. How We Use Your Information
We use your information to operate the platform, process payments and withdrawals, provide customer support, prevent fraud, and improve our services.

3. Sharing of Information
We do not sell your personal information. We may share data with payment processors, service providers, or authorities when required by law or to protect our platform.

4. Data Security
We take reasonable measures to protect your information, but no method of transmission or storage is completely secure.

5. Your Choices
You may update your profile details and contact support to request access, correction, or deletion of personal information where applicable.

6. Changes to This Policy
We may update this Privacy Policy from time to time. Continued use of the platform after changes means you accept the updated policy.

If you have questions about this Privacy Policy, please contact us through the support channels provided on the platform.
TEXT
        );
    }
}
