<?php

namespace App\Http\Middleware;

use App\Services\Discord\DiscordApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateDiscordToken
{
    /**
     * Handle an incoming request.
     *
     * Validates the Discord bearer token and adds the Discord profile to the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['error' => 'No token found'], 401);
        }

        $token = substr($authHeader, 7);

        try {
            $discordProfile = DiscordApiClient::getUserProfile($token);
            $request->attributes->set('discord_profile', $discordProfile);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => "Couldn't verify your Discord account"], 401);
        }

        return $next($request);
    }
}
