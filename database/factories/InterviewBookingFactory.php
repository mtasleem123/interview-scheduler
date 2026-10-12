<?php

namespace Database\Factories;

use App\Models\InterviewBooking;
use App\Models\InterviewSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterviewBooking>
 */
class InterviewBookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'interview_slot_id' => InterviewSlot::factory()->booked(),
            'candidate_name' => fake()->name(),
            'candidate_email' => fake()->unique()->safeEmail(),
            'status' => 'confirmed',
        ];
    }
}
