<?php

namespace Tests\Feature;

use App\Services\DemoVoiceAgent;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Client\Schema\ToolResult;
use Tests\TestCase;

class DemoVoiceAgentTest extends TestCase
{
    /**
     * @var array<int, array{name: string, arguments: array<string, mixed>}>
     */
    protected array $toolCalls = [];

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * A demo agent whose MCP tools are faked, so only the conversation logic is tested.
     */
    protected function agent(): DemoVoiceAgent
    {
        $test = $this;

        return new class($test) extends DemoVoiceAgent
        {
            public function __construct(protected DemoVoiceAgentTest $test) {}

            protected function callSchedulerTool(string $name, array $arguments): ToolResult
            {
                return $this->test->fakeTool($name, $arguments);
            }
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function fakeTool(string $name, array $arguments): ToolResult
    {
        $this->toolCalls[] = ['name' => $name, 'arguments' => $arguments];

        return match ($name) {
            'check_available_slots' => new ToolResult([], false, ['date' => $arguments['date'], 'slots' => [
                ['start_time' => '11:00', 'end_time' => '11:30', 'interviewer_name' => 'Rahul Sharma'],
                ['start_time' => '15:00', 'end_time' => '15:30', 'interviewer_name' => 'Rahul Sharma'],
            ]]),
            'book_interview' => new ToolResult([], false, ['booking_id' => 1025, 'interviewer_name' => 'Rahul Sharma']),
            'get_booking' => $arguments['booking_id'] === 1025
                ? new ToolResult([], false, ['booking_id' => 1025, 'candidate_name' => 'Asha Rao', 'date' => '2026-10-13', 'start_time' => '15:00', 'interviewer_name' => 'Rahul Sharma', 'status' => 'confirmed'])
                : new ToolResult([['type' => 'text', 'text' => 'No booking was found.']], true),
        };
    }

    /**
     * Play a conversation through the agent, one user turn at a time.
     *
     * @param  array<int, string>  $userTurns
     * @return array<int, string> The agent's replies.
     */
    protected function converse(array $userTurns): array
    {
        $conversation = [];
        $replies = [];

        foreach ($userTurns as $turn) {
            $conversation[] = ['role' => 'user', 'content' => $turn];
            $reply = $this->agent()->reply($conversation);
            $conversation[] = ['role' => 'assistant', 'content' => $reply];
            $replies[] = $reply;
        }

        return $replies;
    }

    public function test_it_lists_available_slots_for_tomorrow(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12 09:00', 'Asia/Kolkata'));

        [$reply] = $this->converse(['What slots are available tomorrow?']);

        $this->assertSame('On Tuesday, 13 October, free times are 11 AM and 3 PM. Would you like me to book one? Just tell me the time.', $reply);
        $this->assertSame(['date' => '2026-10-13'], $this->toolCalls[0]['arguments']);
    }

    public function test_it_books_only_after_the_candidate_confirms(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12 09:00', 'Asia/Kolkata'));

        $replies = $this->converse([
            'At 3 P.M at tomorrow',
            'Asha Rao',
            'asha dot rao at gmail dot com',
            'yes',
        ]);

        $this->assertSame('May I have your full name, please?', $replies[0]);
        $this->assertSame('What is your email address?', $replies[1]);
        $this->assertStringStartsWith('Please confirm: an interview on Tuesday, 13 October at 3 PM for Asha Rao, email asha.rao@gmail.com.', $replies[2]);
        $this->assertStringContainsString('Your booking ID is 1025', $replies[3]);

        $bookings = array_values(array_filter($this->toolCalls, fn (array $call): bool => $call['name'] === 'book_interview'));
        $this->assertCount(1, $bookings);
        $this->assertSame([
            'date' => '2026-10-13',
            'start_time' => '15:00',
            'candidate_name' => 'Asha Rao',
            'candidate_email' => 'asha.rao@gmail.com',
        ], $bookings[0]['arguments']);
    }

    public function test_it_does_not_book_when_the_candidate_says_no(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12 09:00', 'Asia/Kolkata'));

        $replies = $this->converse([
            'Book tomorrow at 11 am, my name is Asha Rao and my email is asha@example.com',
            'no',
        ]);

        $this->assertStringStartsWith('Please confirm:', $replies[0]);
        $this->assertStringStartsWith('No problem.', $replies[1]);
        $this->assertNotContains('book_interview', array_column($this->toolCalls, 'name'));
    }

    public function test_it_offers_free_times_when_the_requested_time_is_taken(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12 09:00', 'Asia/Kolkata'));

        [$reply] = $this->converse(['Book tomorrow at 4 pm']);

        $this->assertSame('Sorry, 4 PM is not free on Tuesday, 13 October. Free times are 11 AM and 3 PM. Which time would you like?', $reply);
    }

    public function test_it_looks_up_a_booking_with_id_and_email(): void
    {
        $replies = $this->converse([
            'Check my existing booking',
            '1025',
            'asha.rao@gmail.com',
        ]);

        $this->assertSame('Please tell me your booking ID.', $replies[0]);
        $this->assertSame('What email address did you use for the booking?', $replies[1]);
        $this->assertSame('Here are your booking details. Booking 1025 for Asha Rao is confirmed on Tuesday, 13 October at 3 PM with Rahul Sharma.', $replies[2]);
    }

    public function test_it_asks_again_when_it_does_not_understand_the_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12 09:00', 'Asia/Kolkata'));

        $replies = $this->converse(['What slots are available tomorrow?', 'sx', '11']);

        $this->assertSame('Sorry, I did not catch the time. Please say a time such as 11 AM or 3 PM.', $replies[1]);
        $this->assertSame('May I have your full name, please?', $replies[2]);
    }

    public function test_it_asks_for_yes_or_no_when_the_confirmation_is_unclear(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12 09:00', 'Asia/Kolkata'));

        $replies = $this->converse([
            'Book tomorrow at 11 am, my name is Asha Rao and my email is asha@example.com',
            'hmm',
            'yes',
        ]);

        $this->assertSame('Please say yes to book it, or no to change something.', $replies[1]);
        $this->assertStringContainsString('Your booking ID is 1025', $replies[2]);
        $this->assertCount(1, array_keys(array_column($this->toolCalls, 'name'), 'book_interview'));
    }

    public function test_it_replies_to_greetings_and_thanks_without_calling_tools(): void
    {
        $replies = $this->converse(['Hello', 'thank you']);

        $this->assertStringStartsWith('Hello! I can show available interview slots', $replies[0]);
        $this->assertSame('You are welcome! Is there anything else I can help you with?', $replies[1]);
        $this->assertSame([], $this->toolCalls);
    }
}
