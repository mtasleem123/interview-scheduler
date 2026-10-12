<?php

namespace Tests\Feature;

use App\Mcp\Servers\InterviewSchedulerServer;
use App\Mcp\Tools\BookInterviewTool;
use App\Mcp\Tools\CheckAvailableSlotsTool;
use App\Mcp\Tools\GetBookingTool;
use App\Models\InterviewBooking;
use App\Models\InterviewSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterviewSchedulerMcpTest extends TestCase
{
    use RefreshDatabase;

    protected string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->date = now(config('services.voice_agent.timezone'))->addDays(30)->toDateString();
    }

    public function test_check_available_slots_lists_only_available_slots(): void
    {
        InterviewSlot::factory()->create(['interview_date' => $this->date, 'start_time' => '11:00:00', 'end_time' => '11:30:00']);
        InterviewSlot::factory()->booked()->create(['interview_date' => $this->date, 'start_time' => '15:00:00', 'end_time' => '15:30:00']);

        InterviewSchedulerServer::tool(CheckAvailableSlotsTool::class, ['date' => $this->date])
            ->assertOk()
            ->assertSee('"start_time":"11:00"')
            ->assertDontSee('"start_time":"15:00"');
    }

    public function test_book_interview_books_the_slot_at_the_requested_time(): void
    {
        $slot = InterviewSlot::factory()->create(['interview_date' => $this->date, 'start_time' => '11:00:00', 'end_time' => '11:30:00']);

        InterviewSchedulerServer::tool(BookInterviewTool::class, [
            'date' => $this->date,
            'start_time' => '11:00',
            'candidate_name' => 'Asha Rao',
            'candidate_email' => 'asha.rao@example.com',
        ])
            ->assertOk()
            ->assertSee('"booking_id"');

        $this->assertSame('booked', $slot->fresh()->status);
        $this->assertDatabaseHas('interview_bookings', [
            'interview_slot_id' => $slot->id,
            'candidate_email' => 'asha.rao@example.com',
        ]);
    }

    public function test_book_interview_rejects_a_time_with_no_available_slot(): void
    {
        InterviewSlot::factory()->booked()->create(['interview_date' => $this->date, 'start_time' => '11:00:00', 'end_time' => '11:30:00']);

        InterviewSchedulerServer::tool(BookInterviewTool::class, [
            'date' => $this->date,
            'start_time' => '11:00',
            'candidate_name' => 'Asha Rao',
            'candidate_email' => 'asha.rao@example.com',
        ])->assertHasErrors(["No interview slot is available on {$this->date} at 11:00."]);

        $this->assertDatabaseMissing('interview_bookings', ['candidate_email' => 'asha.rao@example.com']);
    }

    public function test_book_interview_rejects_a_slot_in_the_past(): void
    {
        $yesterday = now(config('services.voice_agent.timezone'))->subDay()->toDateString();
        InterviewSlot::factory()->create(['interview_date' => $yesterday, 'start_time' => '11:00:00', 'end_time' => '11:30:00']);

        InterviewSchedulerServer::tool(BookInterviewTool::class, [
            'date' => $yesterday,
            'start_time' => '11:00',
            'candidate_name' => 'Asha Rao',
            'candidate_email' => 'asha.rao@example.com',
        ])->assertHasErrors(['This interview slot is in the past.']);
    }

    public function test_get_booking_requires_the_matching_email(): void
    {
        $booking = InterviewBooking::factory()->create();

        InterviewSchedulerServer::tool(GetBookingTool::class, [
            'booking_id' => $booking->id,
            'candidate_email' => $booking->candidate_email,
        ])->assertOk()->assertSee($booking->candidate_name);

        InterviewSchedulerServer::tool(GetBookingTool::class, [
            'booking_id' => $booking->id,
            'candidate_email' => 'someone.else@example.com',
        ])->assertHasErrors(['No booking was found for this booking ID and email.']);
    }
}
