<?php

namespace App\Services\Concerns;

use Laravel\Mcp\Client as McpClient;
use Laravel\Mcp\Client\Schema\ToolResult;

/**
 * Talks to the local Interview Scheduler MCP server over stdio.
 *
 * The server process is started lazily on the first tool call, so replies
 * that only ask the candidate a question do not pay the start-up cost.
 */
trait ConnectsToSchedulerMcp
{
    protected ?McpClient $schedulerMcp = null;

    protected function schedulerMcp(): McpClient
    {
        return $this->schedulerMcp ??= McpClient::local(
            config('services.voice_agent.php_binary'),
            [base_path('artisan'), 'mcp:start', 'interview-scheduler'],
        )
            ->withTimeout(15)
            ->connect();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function callSchedulerTool(string $name, array $arguments): ToolResult
    {
        return $this->schedulerMcp()->callTool($name, $arguments);
    }

    protected function disconnectSchedulerMcp(): void
    {
        $this->schedulerMcp?->disconnect();
        $this->schedulerMcp = null;
    }
}
