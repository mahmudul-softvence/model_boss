<?php

namespace Database\Factories;

use App\Models\PrivacyPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrivacyPolicy>
 */
class PrivacyPolicyFactory extends Factory
{
    protected $model = PrivacyPolicy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Privacy Policy',
            'content' => fake()->paragraphs(3, true),
        ];
    }
}
