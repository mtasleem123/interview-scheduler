<?php

namespace App\Mcp\Tools;

use App\Models\InterviewSlot;
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

#[Name('check_available_slots')]
#[Description('List the interview slots that are still available on a given date. Call this before offering or booking any time.')]
#[IsReadOnly]
class CheckAvailableSlotsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, InterviewScheduler $scheduler): Response|ResponseFactory
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $slots = $scheduler->availableSlots($validated['date']);

        if ($slots->isEmpty()) {
            return Response::text("No interview slots are available on {$validated['date']}.");
        }

        return Response::structured([
            'date' => $validated['date'],
            'slots' => $slots->map(fn (InterviewSlot $slot): array => [
                'start_time' => substr($slot->start_time, 0, 5),
                'end_time' => substr($slot->end_time, 0, 5),
                'interviewer_name' => $slot->interviewer_name,
            ])->all(),
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
        ];
    }
}
