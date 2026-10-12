<?php

use App\Models\InterviewBooking;
use App\Models\InterviewSlot;
use Database\Seeders\InterviewSlotSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('interview:reset-demo {--force : Skip the confirmation prompt}', function () {
    if (! $this->option('force') && ! $this->confirm('This deletes ALL bookings and frees every slot. Continue?')) {
        return;
    }

    DB::transaction(function () {
        InterviewBooking::query()->delete();
        InterviewSlot::query()->update(['status' => 'available']);
    });

    $this->call('db:seed', ['--class' => InterviewSlotSeeder::class, '--force' => true]);

    $this->info('Demo data reset: no bookings, and slots are available for the next 5 weekdays.');
})->purpose('Clear all bookings and create fresh interview slots for a demo');
