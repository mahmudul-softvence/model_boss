<?php

namespace Database\Factories;

use App\Enums\ChallengeMode;
use App\Enums\ChallengeStatus;
use App\Models\Challenge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Challenge>
 */
class ChallengeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'challenge_no' => (string) fake()->unique()->numberBetween(100000, 999999),
            'challenger_id' => User::factory(),
            'mode' => ChallengeMode::UNIQUE->value,
            'target_player_id' => User::factory(),
            'game_id' => '1',
            'amount' => 300,
            'memo' => fake()->sentence(),
            'show_real_name' => true,
            'match_date' => now()->addDay()->toDateString(),
            'match_time' => '18:00',
            'status' => ChallengeStatus::PENDING->value,
            'duration_hours' => 24,
            'offer_expires_at' => now()->addDay(),
        ];
    }

    public function offered(): static
    {
        return $this->state(fn () => [
            'status' => ChallengeStatus::OFFERED->value,
            'approved_at' => now(),
        ]);
    }

    public function global(): static
    {
        return $this->state(fn () => [
            'mode' => ChallengeMode::GLOBAL->value,
            'target_player_id' => null,
        ]);
    }
}
