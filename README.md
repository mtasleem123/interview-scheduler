---
title: Interview Scheduler
emoji: 🎙️
colorFrom: blue
colorTo: indigo
sdk: docker
app_port: 10000
pinned: false
short_description: Voice bot to check, book and look up interview slots
---

# Voice-Based AI Interview Scheduler

A browser voice bot that lets candidates check interview slots, book an interview, and look up a booking by speaking.

Built with Laravel 13, MySQL, the Claude API (Anthropic PHP SDK), and MCP (`laravel/mcp`).

## How it works

```
Speech-to-text (browser) → POST /api/voice-agent → Agent (Claude or Demo)
  → MCP tools: check_available_slots | book_interview | get_booking
  → InterviewScheduler service (validation + row lock) → MySQL
  → reply → Text-to-speech (browser)
```

The AI only decides which tool to call. All validation and database writes happen in Laravel.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# set DB_* in .env, then:
php artisan migrate
php artisan db:seed --class=InterviewSlotSeeder
php artisan serve
```

Open `http://localhost:8000/voice-bot` in Chrome or Edge, which support voice input.

## Agent modes

| `VOICE_AGENT_MODE` | Behaviour |
|---|---|
| `auto` (default) | Uses Claude if `ANTHROPIC_API_KEY` is set, otherwise Demo mode |
| `claude` | Always uses Claude |
| `demo` | Rule-based pattern matching with no AI. It uses the same MCP tools and database |

## API

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/interview-slots?date=YYYY-MM-DD` | Available slots |
| POST | `/api/interview-bookings` | Book by slot ID |
| GET | `/api/interview-bookings/{id}?candidate_email=…` | Booking lookup (ID + email required) |
| POST | `/api/voice-agent` | Voice bot conversation turn |

## Key files

- `app/Services/InterviewScheduler.php`: booking rules, locking, and validation
- `app/Mcp/Servers/InterviewSchedulerServer.php` and `app/Mcp/Tools/`: the MCP server and tools
- `app/Services/VoiceAgent.php`: the Claude agent loop
- `app/Services/DemoVoiceAgent.php`: the rule-based demo agent
- `resources/views/voice-bot.blade.php`: the voice UI

## Tests

```bash
php artisan test
```

The tests use in-memory SQLite, which needs the PHP `pdo_sqlite` extension.
