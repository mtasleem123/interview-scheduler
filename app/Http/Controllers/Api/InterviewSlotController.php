<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InterviewScheduler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterviewSlotController extends Controller
{
    public function __construct(protected InterviewScheduler $scheduler) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $slots = $this->scheduler->availableSlots($validated['date']);

        return response()->json([
            'success' => true,
            'message' => $slots->isEmpty()
                ? 'No interview slots are available for this date.'
                : 'Available interview slots retrieved successfully.',
            'data' => $slots,
        ]);
    }
}
