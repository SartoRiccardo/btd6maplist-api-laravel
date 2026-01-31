<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompletionController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\FormatController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\AchievementRoleController;
use Illuminate\Support\Facades\Route;

// Auth endpoints
Route::post('/auth', [AuthController::class, 'authenticate'])
    ->middleware('discord.auth');

Route::put('/read-rules', [AuthController::class, 'readRules'])
    ->middleware('discord.auth');

// Config endpoints
Route::prefix('config')->group(function () {
    Route::get('/', [ConfigController::class, 'index']);
    Route::put('/', [ConfigController::class, 'update'])
        ->middleware('discord.auth');
});

// Formats endpoints
Route::prefix('formats')->group(function () {
    Route::get('/', [FormatController::class, 'index']);

    Route::middleware('discord.auth')->group(function () {
        Route::get('/{id}', [FormatController::class, 'show']);
        Route::put('/{id}', [FormatController::class, 'update']);
    });
});

// Maps endpoints
Route::prefix('maps')->group(function () {
    /**
     * 🟡 Medium: Format-specific queries with conditional filtering logic
     */
    Route::get('/', fn() => response()->noContent(501));

    /**
     * 🔴 Hard: File handling + multiple format validation + permission checks + async webhook + logging
     */
    Route::post('/', fn() => response()->noContent(501));

    /**
     * 🟢 Easy: Simple query with is_deleted filter
     */
    Route::get('/legacy', fn() => response()->noContent(501));

    /**
     * 🟡 Medium: Complex leaderboard query with CTEs + pagination + format filtering
     */
    Route::get('/leaderboard', fn() => response()->noContent(501));

    /**
     * 🟡 Medium: Grouped query with complex response structure (by game/category)
     */
    Route::get('/retro', fn() => response()->noContent(501));

    // Maps submit endpoints
    Route::prefix('submit')->group(function () {
        /**
         * 🔴 Hard: Discord webhook signature validation + complex lookup logic
         */
        Route::get('/', fn() => response()->noContent(501));

        /**
         * 🔴 Hard: File uploads + duplicate checking + Ninja Kiwi API calls + async webhook notifications
         */
        Route::post('/', fn() => response()->noContent(501));

        /**
         * 🔴 Hard: Discord signature validation + message lookup + permission checks + async webhook updates
         */
        Route::delete('/', fn() => response()->noContent(501));

        /**
         * 🟡 Medium: Permission checks + soft delete + async webhook notifications
         */
        Route::delete('/{code}/formats/{format_id}', fn() => response()->noContent(501));
    });

    // Map-specific endpoints
    Route::prefix('{code}')->group(function () {
        /**
         * 🟢 Easy: Single database query by code
         */
        Route::get('/', fn() => response()->noContent(501));

        /**
         * 🔴 Hard: File uploads + database update + async webhook notifications
         */
        Route::put('/', fn() => response()->noContent(501));

        /**
         * 🟡 Medium: Permission checks + soft delete + async logging
         */
        Route::delete('/', fn() => response()->noContent(501));

        // Map completions endpoints
        Route::prefix('completions')->group(function () {
            /**
             * 🟢 Easy: Simple filtered query for authenticated user
             */
            Route::get('/@me', fn() => response()->noContent(501));

            /**
             * 🟡 Medium: Paginated query + format filtering + permission checks
             */
            Route::get('/', fn() => response()->noContent(501));

            /**
             * 🟡 Medium: Permission validation + user completion checking + database insert
             */
            Route::post('/', fn() => response()->noContent(501));

            /**
             * 🔴 Hard: File handling + multiple user validation + permission checks + async logging
             */
            Route::post('/submit', fn() => response()->noContent(501));

            /**
             * 🔴 Hard: Map validation + permission checks + bulk data transfer between maps + async logging
             */
            Route::put('/transfer', fn() => response()->noContent(501));
        });
    });
});

// Server roles endpoints
/**
 * 🔴 Hard: Multiple concurrent Discord API calls + complex filtering + semaphore management
 */
Route::get('/server-roles', fn() => response()->noContent(501));

// Completions endpoints
Route::prefix('completions')->group(function () {
    Route::get('/recent', [CompletionController::class, 'recent']);
    Route::get('/unapproved', [CompletionController::class, 'unapproved']);

    Route::get('/{cid}', [CompletionController::class, 'show']);

    Route::middleware('discord.auth')
        ->group(function () {
            Route::put('/{cid}', [CompletionController::class, 'update']);
            Route::delete('/{cid}', [CompletionController::class, 'destroy']);

            Route::put('/{cid}/accept', [CompletionController::class, 'accept']);
        });
});

// Roles endpoints
Route::prefix('roles')->group(function () {
    Route::get('/', [RoleController::class, 'index']);
});

// Achievement roles endpoints
Route::prefix('roles/achievement')->group(function () {
    Route::get('/', [AchievementRoleController::class, 'index']);
    Route::put('/', [AchievementRoleController::class, 'update'])
        ->middleware('discord.auth');
});

// Users endpoints
Route::prefix('users')->group(function () {
    /**
     * 🟡 Medium: Permission check + user validation + database creation
     */
    Route::post('/', fn() => response()->noContent(501));

    Route::prefix('{uid}')->group(function () {
        /**
         * 🔴 Hard: Surprisingly difficult route to check due to complex queries + optional Ninja Kiwi API call
         */
        Route::get('/', fn() => response()->noContent(501));

        /**
         * 🟡 Medium: Permission check + multiple field updates + validation
         */
        Route::put('/', fn() => response()->noContent(501));

        /**
         * 🟡 Medium: Permission validation + role management + complex response
         */
        Route::patch('/roles', fn() => response()->noContent(501));

        /**
         * 🟢 Easy: Permission check + database update
         */
        Route::post('/ban', fn() => response()->noContent(501));

        /**
         * 🟢 Easy: Permission check + database update
         */
        Route::post('/unban', fn() => response()->noContent(501));

        /**
         * 🟡 Medium: Paginated query with user filtering
         */
        Route::get('/completions', fn() => response()->noContent(501));
    });

    /**
     * 🟡 Medium: Validation + name collision check + external API call + database update
     */
    Route::put('/@me', fn() => response()->noContent(501));

    /**
     * 🟡 Medium: Conditional pagination with multiple query types
     */
    Route::get('/@me/submissions', fn() => response()->noContent(501));
});

/**
 * 🟡 Medium: Text search across multiple entity types (users, maps)
 */
Route::get('/search', fn() => response()->noContent(501));

/**
 * 🔴 Hard: Image processing with PIL + multiple overlays + dynamic text rendering
 */
Route::get('/img/medal-banner/{banner}', fn() => response()->noContent(501));
