<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FormatController;
use Illuminate\Support\Facades\Route;

Route::post('/auth', [AuthController::class, 'authenticate'])
    ->middleware('discord.auth');

Route::put('/read-rules', [AuthController::class, 'readRules'])
    ->middleware('discord.auth');

Route::prefix('formats')->group(function () {
    Route::get('/', [FormatController::class, 'index']);

    Route::middleware('discord.auth')->group(function () {
        Route::get('/{id}', [FormatController::class, 'show']);
        Route::put('/{id}', [FormatController::class, 'update']);
    });
});
