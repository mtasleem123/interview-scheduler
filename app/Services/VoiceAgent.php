<?php

namespace App\Services;

use Anthropic\Client as AnthropicClient;
use App\Services\Concerns\ConnectsToSchedulerMcp;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Client\Primitives\Tool as McpTool;
use Throwable;

/**
 * The agentic part of the voice bot.
 *
 * Claude reads the conversation and decides which tool (if any) to use. The
 * tools are discovered from, and executed by, the local Interview Scheduler
 * MCP server, which validates every call before touching the database.
 */
class VoiceAgent
{
    use ConnectsToSchedulerMcp;

    /**
     * Upper bound on model -> tool -> model round trips for one candidate turn.
     */
    protected const MAX_TOOL_ROUNDS = 6;

    public function __construct(protected AnthropicClient $anthropic) {}

    /**
     * Produce the bot's next spoken reply.
     *
     * @param  array<int, array{role: 'user'|'assistant', content: string}>  $conversation
     */
    public function reply(array $conversation): string
    {
        try {
            $tools = $this->schedulerMcp()->tools()
                ->map(fn (McpTool $tool): array => [
                    'name' => $tool->name,
                    'description' => $tool->description ?? '',
                    'inputSchema' => $tool->inputSchema,
                ])
                ->values()
                ->all();

            $messages = $conversation;

            for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
                $response = $this->anthropic->beta->messages->create(
                    model: config('services.anthropic.model'),
                    maxTokens: 16000,
                    system: $this->systemPrompt(),
                    messages: $messages,
                    tools: $tools,
                    // Low effort keeps spoken replies fast; scheduling is a simple task.
                    outputConfig: ['effort' => 'low'],
                    // If a safety classifier declines, retry on a fallback model server-side.
                    betas: ['server-side-fallback-2026-07-01'],
                    fallbacks: 'default',
                );

                if ($response->stopReason === 'refusal') {
                    return 'Sorry, I can only help with scheduling interviews.';
                }

                if ($response->stopReason !== 'tool_use') {
                    return $this->textOf($response->content)
                        ?: 'Sorry, I did not catch that. Could you say it again?';
                }

                // Append the full assistant turn (including thinking blocks) unchanged.
                $messages[] = ['role' => 'assistant', 'content' => $response->content];
                $messages[] = ['role' => 'user', 'content' => $this->runToolCalls($response->content)];
            }

            return 'Sorry, that is taking longer than expected. Please try again.';
        } finally {
            $this->disconnectSchedulerMcp();
        }
    }

    /**
     * Execute every tool call in an assistant turn through MCP.
     *
     * All results go back in one user message, and failures are returned to
     * Claude as errors so it can explain them to the candidate.
     *
     * @param  array<int, mixed>  $content
     * @return array<int, array<string, mixed>>
     */
    protected function runToolCalls(array $content): array
    {
        $results = [];

        foreach ($content as $block) {
            if ($block->type !== 'tool_use') {
                continue;
            }

            try {
                $result = $this->callSchedulerTool($block->name, (array) $block->input);
                $text = $result->text();
                $isError = $result->isError;
            } catch (Throwable $e) {
                report($e);
                $text = 'The tool failed to run. Ask the candidate to try again shortly.';
                $isError = true;
            }

            $results[] = [
                'type' => 'tool_result',
                'toolUseID' => $block->id,
                'content' => $text !== '' ? $text : 'Done.',
                'isError' => $isError,
            ];
        }

        return $results;
    }

    /**
     * @param  array<int, mixed>  $content
     */
    protected function textOf(array $content): string
    {
        $parts = [];

        foreach ($content as $block) {
            if ($block->type === 'text') {
                $parts[] = $block->text;
            }
        }

        return trim(implode(' ', $parts));
    }

    protected function systemPrompt(): string
    {
        $now = Carbon::now(config('services.voice_agent.timezone'));
        $today = $now->format('l, Y-m-d');
        $tomorrow = $now->copy()->addDay()->format('l, Y-m-d');
        $time = $now->format('H:i');

        return <<<PROMPT
        You are the voice assistant on a company careers website. You help job candidates check interview availability, book an interview slot, and look up an existing booking.

        Today is {$today} and the current time is {$time} (India Standard Time). Tomorrow is {$tomorrow}. Resolve relative dates such as "tomorrow" or "next Monday" from this.

        Your replies are converted to speech, so:
        - Keep each reply to one to three short sentences.
        - Use plain sentences only: no markdown, lists, emoji, or symbols.
        - Say times in 12-hour form, such as "3 PM" or "11:30 AM", and dates as "Tuesday, 13 October".

        How to work:
        - Only offer times that check_available_slots has returned. Never guess availability.
        - To book, you need the date, the start time, the candidate's full name and email. Ask for anything missing, one item at a time.
        - Speech recognition often writes emails as words, for example "asha dot rao at gmail dot com". Convert this to a normal address, spell it back, and ask the candidate to confirm it.
        - Before calling book_interview, read back the date, time, name and email in one sentence and wait for the candidate to say yes. Do not book without that confirmation.
        - After a booking succeeds, tell the candidate their booking ID and ask them to note it down.
        - To look up a booking, ask for the booking ID and the email used to book. Never reveal a booking without both.
        - If a tool returns an error, explain the problem simply and suggest what to do next.
        - If asked about anything unrelated to interview scheduling, politely say you can only help with interviews.
        PROMPT;
    }
}
