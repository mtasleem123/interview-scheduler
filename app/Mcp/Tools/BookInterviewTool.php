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

#[Name('book_interview')]
#[Description('Book an available interview slot for a candidate. Only call this after the candidate has explicitly confirmed the date, time, name and email you read back to them.')]
class BookInterviewTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, InterviewScheduler $scheduler): ResponseFactory
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'candidate_name' => ['required', 'string', 'max:255'],
            'candidate_email' => ['required', 'email', 'max:255'],
        ]);

        $booking = $scheduler->bookByDateTime(
            $validated['date'],
            $validated['start_time'],
            $validated['candidate_name'],
            $validated['candidate_email'],
        );

        return Response::structured([
            'booking_id' => $booking->id,
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
            'date' => $schema->string()
                ->description('The interview date in YYYY-MM-DD format.')
                ->required(),
            'start_time' => $schema->string()
                ->description('The slot start time in 24-hour HH:MM format, e.g. 15:00.')
                ->required(),
            'candidate_name' => $schema->string()
                ->description("The candidate's full name.")
                ->required(),
            'candidate_email' => $schema->string()
                ->description("The candidate's email address, e.g. asha.rao@example.com.")
                ->required(),
        ];
    }
}
