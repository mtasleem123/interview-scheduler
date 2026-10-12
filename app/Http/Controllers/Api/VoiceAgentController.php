<?php

namespace App\Http\Controllers\Api;

use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Http\Controllers\Controller;
use App\Services\DemoVoiceAgent;
use App\Services\VoiceAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class VoiceAgentController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // The browser keeps the conversation and sends it with every turn.
        $validated = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:30'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:2000'],
        ]);

        $messages = array_map(
            fn (array $message): array => [
                'role' => $message['role'],
                'content' => $message['content'],
            ],
            $validated['messages'],
        );

        if ($messages[0]['role'] !== 'user' || end($messages)['role'] !== 'user') {
            throw ValidationException::withMessages([
                'messages' => ['The conversation must start and end with a user message.'],
            ]);
        }

        $mode = $this->mode();

        if ($mode === 'demo') {
            return $this->success(app(DemoVoiceAgent::class)->reply($messages), $mode);
        }

        if (blank(config('services.anthropic.key'))) {
            Log::warning('Voice agent called in claude mode without ANTHROPIC_API_KEY set.');

            return $this->failure('Our assistant is temporarily unavailable. Please try again in a little while.', 503);
        }

        try {
            $reply = app(VoiceAgent::class)->reply($messages);
        } catch (RateLimitException $e) {
            report($e);

            return $this->failure('Our assistant is busy right now. Please try again in a minute.', 429);
        } catch (APIException $e) {
            report($e);

            return $this->failure('Sorry, something went wrong on our side. Please try again.', 502);
        }

        return $this->success($reply, $mode);
    }

    /**
     * Decide whether this request is answered by Claude or by the rule-based demo.
     */
    protected function mode(): string
    {
        $mode = config('services.voice_agent.mode');

        if ($mode === 'auto') {
            return blank(config('services.anthropic.key')) ? 'demo' : 'claude';
        }

        return $mode === 'demo' ? 'demo' : 'claude';
    }

    protected function success(string $reply, string $mode): JsonResponse
    {
        return response()->json([
            'success' => true,
            'reply' => $reply,
            'mode' => $mode,
        ]);
    }

    protected function failure(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
