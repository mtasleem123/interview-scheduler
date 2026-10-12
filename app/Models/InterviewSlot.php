<?php

namespace App\Models;

use Database\Factories\InterviewSlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InterviewSlot extends Model
{
    /** @use HasFactory<InterviewSlotFactory> */
    use HasFactory;

    protected $fillable = [
        'interviewer_name',
        'interview_date',
        'start_time',
        'end_time',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'interview_date' => 'date',
        ];
    }

    public function booking(): HasOne
    {
        return $this->hasOne(InterviewBooking::class);
    }
}
