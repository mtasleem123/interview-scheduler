<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interview_bookings', function (Blueprint $table) {
            $table->foreignId('interview_slot_id')
                ->constrained('interview_slots')
                ->cascadeOnDelete();

            $table->string('candidate_name');
            $table->string('candidate_email');
            $table->string('status')->default('confirmed');

            $table->unique(
                ['interview_slot_id', 'candidate_email'],
                'unique_candidate_slot'
            );
        });
    }

    public function down(): void
    {
        Schema::table('interview_bookings', function (Blueprint $table) {
            $table->dropUnique('unique_candidate_slot');
            $table->dropForeign(['interview_slot_id']);

            $table->dropColumn([
                'interview_slot_id',
                'candidate_name',
                'candidate_email',
                'status',
            ]);
        });
    }
};
