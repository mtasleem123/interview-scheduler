<?php

use App\Http\Controllers\Api\InterviewBookingController;
use App\Http\Controllers\Api\InterviewSlotController;
use App\Http\Controllers\Api\VoiceAgentController;
use Illuminate\Support\Facades\Route;

Route::get('/interview-slots', [
    InterviewSlotController::class,
    'index',
]);

Route::post('/interview-bookings', [
    InterviewBookingController::class,
    'store',
]);

Route::get('/interview-bookings/{booking}', [
    InterviewBookingController::class,
    'show',
])->whereNumber('booking');

// Each turn calls a paid LLM API, so limit how often one client can call it.
Route::post('/voice-agent', VoiceAgentController::class)
    ->middleware('throttle:20,1');
