<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => fake()->randomDigitNotNull(),
            'manager_id' => fake()->numberBetween(1, 2),
            'reason_id' => fake()->randomDigitNotNull(),
            'weight' => fake()->randomDigitNotNull(),
            'mark' => fake()->randomElements([1,2,3,4,5,6,7,8,9,10], null),
        ];
    }
}
