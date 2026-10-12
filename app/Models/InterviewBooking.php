<?php

namespace App\Models;

use Database\Factories\InterviewBookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewBooking extends Model
{
    /** @use HasFactory<InterviewBookingFactory> */
    use HasFactory;

    protected $fillable = [
        'interview_slot_id',
        'candidate_name',
        'candidate_email',
        'status',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(
            InterviewSlot::class,
            'interview_slot_id'
        );
    }
}
