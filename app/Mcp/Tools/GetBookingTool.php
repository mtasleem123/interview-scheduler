<?php

namespace App\Mcp\Tools;

use App\Services\InterviewScheduler;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_booking')]
#[Description('Look up an existing interview booking. Needs both the booking ID and the email address used to book it.')]
#[IsReadOnly]
class GetBookingTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, InterviewScheduler $scheduler): Response|ResponseFactory
    {
        $validated = $request->validate([
            'booking_id' => ['required', 'integer'],
            'candidate_email' => ['required', 'email', 'max:255'],
        ]);

        $booking = $scheduler->findBooking($validated['booking_id'], $validated['candidate_email']);

        if (! $booking) {
            return Response::error('No booking was found for this booking ID and email.');
        }

        return Response::structured([
            'booking_id' => $booking->id,
            'candidate_name' => $booking->candidate_name,
            'date' => $booking->slot->interview_date->format('Y-m-d'),
            'start_time' => substr($booking->slot->start_time, 0, 5),
            'end_time' => substr($booking->slot->end_time, 0, 5),
            'interviewer_name' => $booking->slot->interviewer_name,
            'status' => $booking->status,
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'booking_id' => $schema->integer()
                ->description('The numeric booking ID given when the interview was booked.')
                ->required(),
            'candidate_email' => $schema->string()
                ->description('The email address used for the booking.')
                ->required(),
        ];
    }
}
