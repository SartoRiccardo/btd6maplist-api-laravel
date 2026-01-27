<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RegisterDiscordUser
{
    /**
     * Handle an incoming request.
     *
     * Ensures the Discord user exists in the database and adds the authenticated user to the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $discordProfile = $request->attributes->get('discord_profile');

        if (!$discordProfile) {
            return response()->json(['error' => 'Discord profile not found'], 401);
        }

        $user = User::find($discordProfile['id']);

        if (!$user) {
            $user = User::create([
                'discord_id' => $discordProfile['id'],
                'name' => $discordProfile['username'],
                'has_seen_popup' => false,
                'is_banned' => false,
            ]);
        }

        $request->attributes->set('authenticated_user', $user);

        return $next($request);
    }
}
