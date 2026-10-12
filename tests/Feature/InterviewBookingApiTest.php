<?php

namespace Tests\Feature;

use App\Models\InterviewBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterviewBookingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_can_view_their_booking_with_matching_email(): void
    {
        $booking = InterviewBooking::factory()->create();

        $response = $this->getJson("/api/interview-bookings/{$booking->id}?candidate_email={$booking->candidate_email}");

        $response->assertOk()
            ->assertJsonPath('data.id', $booking->id)
            ->assertJsonPath('data.slot.id', $booking->interview_slot_id);
    }

    public function test_booking_is_not_returned_when_email_does_not_match(): void
    {
        $booking = InterviewBooking::factory()->create();

        $response = $this->getJson("/api/interview-bookings/{$booking->id}?candidate_email=someone.else@example.com");

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_booking_lookup_requires_an_email(): void
    {
        $booking = InterviewBooking::factory()->create();

        $response = $this->getJson("/api/interview-bookings/{$booking->id}");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('candidate_email');
    }
}
