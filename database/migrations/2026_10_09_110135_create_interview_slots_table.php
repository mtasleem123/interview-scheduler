<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_slots', function (Blueprint $table) {
            $table->id();

            $table->string('interviewer_name');
            $table->date('interview_date');
            $table->time('start_time');
            $table->time('end_time');

            $table->string('status')->default('available');

            $table->timestamps();

            $table->unique(
                ['interviewer_name', 'interview_date', 'start_time'],
                'slot_interviewer_date_time_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_slots');
    }
};
