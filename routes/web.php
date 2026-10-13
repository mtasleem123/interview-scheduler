<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/voice-bot');

Route::view('/voice-bot', 'voice-bot')->name('voice-bot');
