<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_home_page_redirects_to_the_voice_bot(): void
    {
        $this->get('/')->assertRedirect('/voice-bot');
    }

    public function test_the_voice_bot_page_loads(): void
    {
        $this->get('/voice-bot')->assertOk()->assertSee('Interview Assistant');
    }
}
