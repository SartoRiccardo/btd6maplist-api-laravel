<?php

use Illuminate\Support\Facades\Route;

// Auth endpoints
Route::post('/auth', [AuthController::class, 'authenticate'])
    ->middleware('discord.auth');

Route::put('/read-rules', [AuthController::class, 'readRules'])
    ->middleware('discord.auth');

// Config endpoints
Route::prefix('config')
    ->controller(ConfigController::class)
    ->group(function () {
        Route::get('/', 'index');
        Route::put('/', 'update')
            ->middleware('discord.auth');
    });

// Format endpoints
Route::prefix('formats')
    ->controller(FormatController::class)
    ->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');
        Route::put('/', 'update')
            ->middleware('discord.auth');
        Route::get('/{id}/leaderboard', 'leaderboard');
    });

// Retro Map endpoints
Route::prefix('maps/retro')
    ->controller(RetroMapController::class)
    ->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');

        Route::middleware('discord.auth')
            ->group(function () {
                Route::post('/', 'save');
                Route::put('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
            });
    });

// Map endpoints
Route::prefix('maps')
    ->controller(MapController::class)
    ->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');

        Route::middleware('discord.auth')
            ->group(function () {
                Route::post('/submissions', 'submit');

                Route::post('/', 'save');
                Route::put('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
            });
    });

// Completion endpoints
Route::prefix('completions')
    ->controller(CompletionController::class)
    ->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');

        Route::middleware('discord.auth')
            ->group(function () {
                Route::post('/submissions', 'submit');
                Route::put('/transfer', 'transfer');

                Route::post('/', 'save');
                Route::put('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
            });
    });

Route::post('/server-roles', [DiscordUtilitiesController::class, 'serverRoles']);

// Platform Roles endpoints
Route::prefix('roles/platform')
    ->controller(PlatformRoleController::class)
    ->group(function () {
        Route::get('/', 'index');
    });

// Achievement Roles endpoints
Route::prefix('roles/achievement')
    ->controller(AchievementRoleController::class)
    ->group(function () {
        Route::get('/', 'index');

        Route::middleware('discord.auth')
            ->group(function () {
                Route::put('/', 'update');
            });
    });

// Users endpoints
Route::prefix('users')
    ->controller(UserController::class)
    ->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');

        Route::middleware('discord.auth')
            ->group(function () {
                Route::put('/{id}', 'update');
                Route::put('/{id}/ban', 'banUser');
                Route::put('/{id}/unban', 'unbanUser');
            });
    });

Route::get('/search', [SearchController::class, 'search']);

Route::get('/img/medal-banner/{banner}', [ImageGeneratorController::class, 'medalBanner']);
