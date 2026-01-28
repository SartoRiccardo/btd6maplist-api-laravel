<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FormatController;
use Illuminate\Support\Facades\Route;

Route::post('/auth', [AuthController::class, 'authenticate'])
    ->middleware(['discord.auth', 'discord.register']);

Route::put('/read-rules', [AuthController::class, 'readRules'])
    ->middleware(['discord.auth', 'discord.register']);

Route::get('/formats', [FormatController::class, 'index']);
