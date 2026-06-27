<?php

namespace Database\Factories;

use App\Models\PromotionalTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromotionalTerm>
 */
class PromotionalTermFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prize' => fake()->numberBetween(100, 5000),
            'list' => [
                fake()->sentence(),
                fake()->sentence(),
                fake()->sentence(),
            ],
        ];
    }
}
