<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InterviewScheduler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterviewBookingController extends Controller
{
    public function __construct(protected InterviewScheduler $scheduler) {}

    public function store(Request $request): JsonResponse
    {
        // Validate candidate details.
        $validated = $request->validate([
            'interview_slot_id' => [
                'required',
                'integer',
                'exists:interview_slots,id',
            ],
            'candidate_name' => [
                'required',
                'string',
                'max:255',
            ],
            'candidate_email' => [
                'required',
                'email',
                'max:255',
            ],
        ]);

        // The scheduler locks the slot while checking and booking it.
        $booking = $this->scheduler->bookSlot(
            $validated['interview_slot_id'],
            $validated['candidate_name'],
            $validated['candidate_email'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Interview booked successfully.',
            'data' => $booking,
        ], 201);
    }

    public function show(Request $request, int $booking): JsonResponse
    {
        // The email proves the caller owns this booking.
        $validated = $request->validate([
            'candidate_email' => ['required', 'email', 'max:255'],
        ]);

        $found = $this->scheduler->findBooking($booking, $validated['candidate_email']);

        if (! $found) {
            return response()->json([
                'success' => false,
                'message' => 'No booking was found for this ID and email.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Booking retrieved successfully.',
            'data' => $found,
        ]);
    }
}
