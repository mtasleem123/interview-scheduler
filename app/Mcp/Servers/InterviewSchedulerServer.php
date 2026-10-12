<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\BookInterviewTool;
use App\Mcp\Tools\CheckAvailableSlotsTool;
use App\Mcp\Tools\GetBookingTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Interview Scheduler')]
#[Version('0.1.0')]
#[Instructions('Tools for checking interview availability, booking interview slots, and looking up existing bookings.')]
class InterviewSchedulerServer extends Server
{
    protected array $tools = [
        CheckAvailableSlotsTool::class,
        BookInterviewTool::class,
        GetBookingTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
