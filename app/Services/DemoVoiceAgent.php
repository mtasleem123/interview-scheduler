<?php

namespace App\Services;

use App\Services\Concerns\ConnectsToSchedulerMcp;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * A rule-based stand-in for the Claude agent, used when no API key is set.
 *
 * It understands a small set of phrases with simple pattern matching instead
 * of an LLM, but it uses the same MCP tools, so slot checks and bookings
 * follow exactly the same path through the backend.
 */
class DemoVoiceAgent
{
    use ConnectsToSchedulerMcp;

    protected const ASK_DATE = 'Which date would you like? You can say today, tomorrow, or a day such as Monday.';

    protected const ASK_NAME = 'May I have your full name, please?';

    protected const ASK_EMAIL = 'What is your email address?';

    protected const ASK_BOOKING_ID = 'Please tell me your booking ID.';

    protected const ASK_CONFIRM = 'Please confirm:';

    protected const FREE_TIMES = 'Free times are';

    protected const BOOKED = 'Your booking ID is';

    protected const FOUND = 'Here are your booking details.';

    protected const HELP = 'I can show available interview slots, book an interview, or check your existing booking. What would you like to do?';

    /**
     * Produce the bot's next reply.
     *
     * @param  array<int, array{role: 'user'|'assistant', content: string}>  $conversation
     */
    public function reply(array $conversation): string
    {
        $state = $this->readConversation($conversation);

        try {
            return match ($state['intent']) {
                'book' => $this->handleBooking($state),
                'lookup' => $this->handleLookup($state),
                'slots' => $this->handleSlots($state),
                default => self::HELP,
            };
        } finally {
            $this->disconnectSchedulerMcp();
        }
    }

    /**
     * Collect what the candidate has said so far: intent, date, time, name, email, booking ID.
     *
     * Only messages after the last finished booking or lookup are used, so a
     * new request starts fresh. The bot's previous question gives meaning to
     * short answers such as "yes", "11" or a bare name.
     *
     * @param  array<int, array{role: string, content: string}>  $conversation
     * @return array{intent: ?string, date: ?string, time: ?string, name: ?string, email: ?string, booking_id: ?int, confirmed: ?bool}
     */
    protected function readConversation(array $conversation): array
    {
        $start = 0;

        foreach ($conversation as $index => $message) {
            if ($message['role'] === 'assistant'
                && (str_contains($message['content'], self::BOOKED) || str_starts_with($message['content'], self::FOUND))) {
                $start = $index + 1;
            }
        }

        $state = ['intent' => null, 'date' => null, 'time' => null, 'name' => null, 'email' => null, 'booking_id' => null, 'confirmed' => null];
        $lastBotMessage = '';

        foreach (array_slice($conversation, $start) as $message) {
            if ($message['role'] === 'assistant') {
                $lastBotMessage = $message['content'];

                continue;
            }

            $text = mb_strtolower(trim($message['content']));
            $email = $this->parseEmail($text);
            $textWithoutEmail = $this->removeEmails($text);

            $state['email'] = $email ?? $state['email'];
            $state['date'] = $this->parseDate($textWithoutEmail) ?? $state['date'];
            $state['time'] = $this->parseTime($textWithoutEmail, $lastBotMessage) ?? $state['time'];
            $state['name'] = $this->parseName($textWithoutEmail, $lastBotMessage) ?? $state['name'];
            $state['booking_id'] = $this->parseBookingId($textWithoutEmail, $lastBotMessage) ?? $state['booking_id'];
            $state['confirmed'] = str_starts_with($lastBotMessage, self::ASK_CONFIRM) ? $this->parseYesNo($text) : null;
            $state['intent'] = $this->parseIntent($text) ?? $state['intent'];

            // A time mentioned while browsing slots (or with no clear intent) means "book it".
            if (in_array($state['intent'], [null, 'slots'], true) && $this->parseTime($textWithoutEmail, $lastBotMessage)) {
                $state['intent'] = 'book';
            }
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function handleSlots(array $state): string
    {
        if (! $state['date']) {
            return self::ASK_DATE;
        }

        $times = $this->availableTimes($state['date']);
        $day = $this->speakDate($state['date']);

        if ($times === []) {
            return "Sorry, there are no free interview slots on {$day}. Would you like to try another date?";
        }

        return "On {$day}, ".lcfirst(self::FREE_TIMES).' '.$this->speakList($times).'. Would you like me to book one? Just tell me the time.';
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function handleBooking(array $state): string
    {
        if (! $state['date']) {
            return self::ASK_DATE;
        }

        $day = $this->speakDate($state['date']);

        if ($state['confirmed'] !== true) {
            $times = $this->availableTimes($state['date']);

            if ($times === []) {
                return "Sorry, there are no free interview slots on {$day}. Would you like to try another date?";
            }

            if (! $state['time']) {
                return "Which time would you like on {$day}? ".self::FREE_TIMES.' '.$this->speakList($times).'.';
            }

            if (! in_array($state['time'], $times, true)) {
                return 'Sorry, '.$this->speakTime($state['time'])." is not free on {$day}. ".self::FREE_TIMES.' '.$this->speakList($times).'. Which time would you like?';
            }
        }

        $time = $this->speakTime($state['time']);

        if (! $state['name']) {
            return self::ASK_NAME;
        }

        if (! $state['email']) {
            return self::ASK_EMAIL;
        }

        if ($state['confirmed'] === false) {
            return 'No problem. Tell me what you would like to change: the date, the time, your name, or your email.';
        }

        if ($state['confirmed'] !== true) {
            return self::ASK_CONFIRM." an interview on {$day} at {$time} for {$state['name']}, email {$state['email']}. Shall I book it? Please say yes or no.";
        }

        $result = $this->callTool('book_interview', [
            'date' => $state['date'],
            'start_time' => $state['time'],
            'candidate_name' => $state['name'],
            'candidate_email' => $state['email'],
        ]);

        if ($result['error'] !== null) {
            return "Sorry, I could not book that. {$result['error']} Would you like to try another time?";
        }

        $booking = $result['data'];

        return "Done! Your interview is booked for {$day} at {$time} with {$booking['interviewer_name']}. ".self::BOOKED." {$booking['booking_id']}. Please note it down.";
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function handleLookup(array $state): string
    {
        if (! $state['booking_id']) {
            return self::ASK_BOOKING_ID;
        }

        if (! $state['email']) {
            return 'What email address did you use for the booking?';
        }

        $result = $this->callTool('get_booking', [
            'booking_id' => $state['booking_id'],
            'candidate_email' => $state['email'],
        ]);

        if ($result['error'] !== null) {
            return "Sorry, I could not find booking {$state['booking_id']} with that email. Please check and tell me your booking ID again.";
        }

        $booking = $result['data'];

        return self::FOUND." Booking {$booking['booking_id']} for {$booking['candidate_name']} is {$booking['status']} on "
            .$this->speakDate($booking['date']).' at '.$this->speakTime($booking['start_time'])." with {$booking['interviewer_name']}.";
    }

    /**
     * @return array<int, string> Start times in HH:MM format.
     */
    protected function availableTimes(string $date): array
    {
        $result = $this->callTool('check_available_slots', ['date' => $date]);

        return array_column($result['data']['slots'] ?? [], 'start_time');
    }

    /**
     * Call an MCP tool and normalise the result.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{data: array<string, mixed>, error: ?string}
     */
    protected function callTool(string $name, array $arguments): array
    {
        try {
            $result = $this->callSchedulerTool($name, $arguments);
        } catch (Throwable $e) {
            report($e);

            return ['data' => [], 'error' => 'The system is not responding right now.'];
        }

        return [
            'data' => $result->structuredContent ?? [],
            'error' => $result->isError ? $result->text() : null,
        ];
    }

    // ---------------------------------------------------------------
    // Understanding the candidate (simple pattern matching).
    // ---------------------------------------------------------------

    protected function parseIntent(string $text): ?string
    {
        return match (true) {
            (bool) preg_match('/\b(existing|my booking|check (my )?booking|booking (status|details)|look ?up|when is my interview|my interview (date|time))\b/', $text) => 'lookup',
            (bool) preg_match('/\b(book|schedule|reserve)\b/', $text) => 'book',
            (bool) preg_match('/\b(slots?|available|availability|free)\b/', $text) => 'slots',
            default => null,
        };
    }

    protected function parseDate(string $text): ?string
    {
        $now = Carbon::now(config('services.voice_agent.timezone'))->startOfDay();

        if (str_contains($text, 'day after tomorrow')) {
            return $now->addDays(2)->toDateString();
        }

        if (preg_match('/\b(today|tomorrow)\b/', $text, $match)) {
            return $match[1] === 'today' ? $now->toDateString() : $now->addDay()->toDateString();
        }

        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $text, $match)) {
            return $match[1];
        }

        $months = 'jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?';

        if (preg_match("/\b(\d{1,2})(?:st|nd|rd|th)?\s+(?:of\s+)?({$months})\b/", $text, $match)
            || preg_match("/\b({$months})\s+(\d{1,2})(?:st|nd|rd|th)?\b/", $text, $reversed)) {
            [$dayNumber, $month] = isset($reversed) && $reversed ? [$reversed[2], $reversed[1]] : [$match[1], $match[2]];

            try {
                $date = Carbon::parse("{$dayNumber} {$month} {$now->year}", config('services.voice_agent.timezone'));
            } catch (Throwable) {
                return null;
            }

            return ($date->lt($now) ? $date->addYear() : $date)->toDateString();
        }

        if (preg_match('/\b(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/', $text, $match)) {
            return $now->next(ucfirst($match[1]))->toDateString();
        }

        return null;
    }

    /**
     * @return ?string Time in 24-hour HH:MM format.
     */
    protected function parseTime(string $text, string $lastBotMessage): ?string
    {
        if (str_contains($text, 'noon')) {
            return '12:00';
        }

        if (preg_match('/\b(\d{1,2})(?:[:.](\d{2}))?\s*([ap])\.?\s?m\b\.?/', $text, $match)) {
            $hour = (int) $match[1] % 12 + ($match[3] === 'p' ? 12 : 0);

            return $this->formatTime($hour, (int) ($match[2] ?? 0));
        }

        if (preg_match('/\b([01]?\d|2[0-3]):([0-5]\d)\b/', $text, $match)) {
            return $this->formatTime((int) $match[1], (int) $match[2]);
        }

        // "at 3" or a bare "11" in reply to "Which time would you like?"
        if (preg_match('/\bat (\d{1,2})\b(?!\s*(?:st|nd|rd|th|[a-z]{3}))/', $text, $match)
            || (str_contains($lastBotMessage, self::FREE_TIMES) && preg_match('/^\D*\b(\d{1,2})\b\D*$/', $text, $match))) {
            $hour = (int) $match[1];

            // Interviews run in office hours, so "at 3" means 3 PM.
            return $hour >= 1 && $hour <= 12 ? $this->formatTime($hour < 8 ? $hour + 12 : $hour, 0) : null;
        }

        return null;
    }

    protected function parseEmail(string $text): ?string
    {
        $pattern = '/[a-z0-9._%+-]+@[a-z0-9-]+(?:\.[a-z0-9-]+)+/';

        if (preg_match($pattern, $text, $match) || preg_match($pattern, $this->spokenEmailToWritten($text), $match)) {
            return $match[0];
        }

        return null;
    }

    /**
     * Strip written and spoken email addresses so their words and digits are not misread as a time or name.
     */
    protected function removeEmails(string $text): string
    {
        return preg_replace([
            '/[a-z0-9._%+-]+@[a-z0-9-]+(?:\.[a-z0-9-]+)+/',
            '/[a-z0-9._-]+(?:\s+dot\s+[a-z0-9_-]+)*\s+at(?:\s+the\s+rate)?\s+[a-z0-9-]+(?:\s+dot\s+[a-z0-9-]+)+/',
        ], ' ', $text);
    }

    /**
     * Speech recognition writes "asha dot rao at gmail dot com"; turn it into an address.
     */
    protected function spokenEmailToWritten(string $text): string
    {
        $text = preg_replace('/\s+dot\s+/', '.', $text);
        $text = preg_replace('/\s+at(?:\s+the\s+rate)?\s+/', '@', $text);

        return preg_replace('/\s*([@.])\s*/', '$1', $text);
    }

    protected function parseName(string $text, string $lastBotMessage): ?string
    {
        if (preg_match("/\b(?:my name is|name is)\s+([a-z][a-z' -]{1,50}?)(?=\s+(?:and|my|email)\b|[,.]|$)/", $text, $match)) {
            return $this->cleanName($match[1]);
        }

        if ($lastBotMessage === self::ASK_NAME) {
            return $this->cleanName(preg_replace("/^(?:it's|it is|i am|i'm)\s+/", '', $text));
        }

        return null;
    }

    protected function cleanName(string $name): ?string
    {
        $name = trim(preg_replace("/[^a-z' -]/", '', $name));

        return $name === '' ? null : ucwords($name);
    }

    protected function parseBookingId(string $text, string $lastBotMessage): ?int
    {
        if (preg_match('/\b(?:booking|id|number)\D{0,15}?(\d{1,9})\b/', $text, $match)
            || ($lastBotMessage === self::ASK_BOOKING_ID && preg_match('/\b(\d{1,9})\b/', $text, $match))) {
            return (int) $match[1];
        }

        return null;
    }

    protected function parseYesNo(string $text): ?bool
    {
        return match (true) {
            (bool) preg_match('/\b(yes|yeah|yep|sure|confirm|correct|ok|okay|go ahead|please do)\b/', $text) => true,
            (bool) preg_match('/\b(no|nope|wrong|change|cancel)\b/', $text) => false,
            default => null,
        };
    }

    // ---------------------------------------------------------------
    // Speaking dates and times naturally.
    // ---------------------------------------------------------------

    protected function formatTime(int $hour, int $minute): ?string
    {
        return $hour <= 23 && $minute <= 59 ? sprintf('%02d:%02d', $hour, $minute) : null;
    }

    protected function speakDate(string $date): string
    {
        return Carbon::parse($date)->format('l, j F');
    }

    protected function speakTime(string $time): string
    {
        $parsed = Carbon::createFromFormat('H:i', substr($time, 0, 5));

        return $parsed->format($parsed->minute === 0 ? 'g A' : 'g:i A');
    }

    /**
     * @param  array<int, string>  $times
     */
    protected function speakList(array $times): string
    {
        $spoken = array_map($this->speakTime(...), $times);
        $last = array_pop($spoken);

        return $spoken === [] ? $last : implode(', ', $spoken).' and '.$last;
    }
}
