<?php

use App\Mcp\Servers\InterviewSchedulerServer;
use Laravel\Mcp\Facades\Mcp;

// Local (stdio) only: the voice agent starts it with `php artisan mcp:start interview-scheduler`.
// It is deliberately not exposed over HTTP, because its tools can create bookings.
Mcp::local('interview-scheduler', InterviewSchedulerServer::class);
