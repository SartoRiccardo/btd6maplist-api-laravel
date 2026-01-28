<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DiscordAuth
{
    /**
     * Handle an incoming request.
     *
     * Ensures the user is authenticated via Discord.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->guard('discord')->check()) {
            return response()->json(['error' => 'No token found or invalid token'], 401);
        }

        return $next($request);
    }
}
