<?php

namespace App\Services;

use App\Models\InterviewBooking;
use App\Models\InterviewSlot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single place where interview scheduling rules live.
 *
 * The REST controllers and the MCP tools both call this class, so the same
 * validation applies whether a request comes from a form, an API client, or
 * the AI agent. The LLM never touches the database directly.
 */
class InterviewScheduler
{
    /**
     * Get the available slots for a date, earliest first.
     *
     * @return Collection<int, InterviewSlot>
     */
    public function availableSlots(string $date): Collection
    {
        return InterviewSlot::query()
            ->whereDate('interview_date', $date)
            ->where('status', 'available')
            ->orderBy('start_time')
            ->get([
                'id',
                'interviewer_name',
                'interview_date',
                'start_time',
                'end_time',
                'status',
            ])
            ->reject(fn (InterviewSlot $slot): bool => $this->hasStarted($slot))
            ->values();
    }

    /**
     * Book a specific slot, locking it so two candidates cannot take it at once.
     *
     * @throws ValidationException
     */
    public function bookSlot(int $slotId, string $candidateName, string $candidateEmail): InterviewBooking
    {
        return DB::transaction(function () use ($slotId, $candidateName, $candidateEmail): InterviewBooking {
            $slot = InterviewSlot::whereKey($slotId)->lockForUpdate()->first();

            if (! $slot) {
                throw ValidationException::withMessages([
                    'interview_slot_id' => ['The selected interview slot does not exist.'],
                ]);
            }

            if ($slot->status !== 'available') {
                throw ValidationException::withMessages([
                    'interview_slot_id' => ['This interview slot is already booked.'],
                ]);
            }

            if ($this->hasStarted($slot)) {
                throw ValidationException::withMessages([
                    'interview_slot_id' => ['This interview slot is in the past.'],
                ]);
            }

            $booking = InterviewBooking::create([
                'interview_slot_id' => $slot->id,
                'candidate_name' => $candidateName,
                'candidate_email' => $candidateEmail,
                'status' => 'confirmed',
            ]);

            $slot->update(['status' => 'booked']);

            return $booking->setRelation('slot', $slot);
        });
    }

    /**
     * Book the first available slot that starts at the given date and time.
     *
     * Voice users say "tomorrow at 11", not a slot ID, so this resolves the
     * spoken time to a slot and then books it through the locked path.
     *
     * @throws ValidationException
     */
    public function bookByDateTime(string $date, string $startTime, string $candidateName, string $candidateEmail): InterviewBooking
    {
        $slot = InterviewSlot::query()
            ->whereDate('interview_date', $date)
            ->where('start_time', Carbon::createFromFormat('H:i', $startTime)->format('H:i:s'))
            ->where('status', 'available')
            ->orderBy('id')
            ->first();

        if (! $slot) {
            throw ValidationException::withMessages([
                'start_time' => ["No interview slot is available on {$date} at {$startTime}."],
            ]);
        }

        return $this->bookSlot($slot->id, $candidateName, $candidateEmail);
    }

    /**
     * Find a booking, but only for the candidate who made it.
     *
     * Requiring the email as well as the ID stops anyone from reading other
     * candidates' bookings by guessing sequential booking IDs.
     */
    public function findBooking(int $bookingId, string $candidateEmail): ?InterviewBooking
    {
        return InterviewBooking::with('slot')
            ->whereKey($bookingId)
            ->where('candidate_email', $candidateEmail)
            ->first();
    }

    /**
     * Get the timezone that slot dates and times are stored in.
     */
    public function timezone(): string
    {
        return config('services.voice_agent.timezone');
    }

    protected function hasStarted(InterviewSlot $slot): bool
    {
        $startsAt = Carbon::parse(
            $slot->interview_date->format('Y-m-d').' '.$slot->start_time,
            $this->timezone(),
        );

        return $startsAt->isPast();
    }
}
