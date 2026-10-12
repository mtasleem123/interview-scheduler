<?php

namespace Database\Factories;

use App\Models\InterviewSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterviewSlot>
 */
class InterviewSlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hour = fake()->numberBetween(10, 16);

        return [
            'interviewer_name' => fake()->name(),
            'interview_date' => now('Asia/Kolkata')->addDays(fake()->numberBetween(1, 7))->toDateString(),
            'start_time' => sprintf('%02d:00:00', $hour),
            'end_time' => sprintf('%02d:30:00', $hour),
            'status' => 'available',
        ];
    }

    /**
     * Indicate that the slot has already been booked.
     */
    public function booked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'booked',
        ]);
    }
}
