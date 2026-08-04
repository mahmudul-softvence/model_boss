<?php

namespace Database\Factories;

use App\Models\TermsAndCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TermsAndCondition>
 */
class TermsAndConditionFactory extends Factory
{
    protected $model = TermsAndCondition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Terms and Conditions',
            'content' => fake()->paragraphs(3, true),
        ];
    }
}
