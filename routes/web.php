<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/voice-bot', 'voice-bot')->name('voice-bot');
