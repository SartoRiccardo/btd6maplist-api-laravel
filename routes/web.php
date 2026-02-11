<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\OAuthController;

Route::get('/', function () {
    return response()->json(['message' => 'BTD6 Maplist API']);
});

Route::prefix('web/oauth2/discord')
    ->controller(OAuthController::class)
    ->group(function () {
        Route::post('/login', 'discordRedirect');
        Route::post('/callback', 'discordCallback');
    });
