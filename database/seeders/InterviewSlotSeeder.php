<?php

namespace Database\Seeders;

use App\Models\InterviewSlot;
use Illuminate\Database\Seeder;

class InterviewSlotSeeder extends Seeder
{
    /**
     * Create half-hour slots from 10 AM to 4:30 PM on the next five weekdays.
     *
     * Safe to run repeatedly: existing slots are left untouched.
     */
    public function run(): void
    {
        $date = now(config('services.voice_agent.timezone'))->startOfDay();
        $daysSeeded = 0;

        while ($daysSeeded < 5) {
            $date->addDay();

            if ($date->isWeekend()) {
                continue;
            }

            foreach (['10:00', '11:00', '12:00', '14:00', '15:00', '16:00'] as $startTime) {
                InterviewSlot::firstOrCreate(
                    [
                        'interviewer_name' => 'Rahul Sharma',
                        'interview_date' => $date->toDateString(),
                        'start_time' => "{$startTime}:00",
                    ],
                    [
                        'end_time' => date('H:i:s', strtotime("{$startTime} +30 minutes")),
                        'status' => 'available',
                    ],
                );
            }

            $daysSeeded++;
        }
    }
}
