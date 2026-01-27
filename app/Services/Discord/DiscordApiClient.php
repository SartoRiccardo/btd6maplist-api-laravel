<?php

namespace App\Services\Discord;

class DiscordApiClient
{
    protected static ?array $fakeProfile = null;

    /**
     * Validate Discord token and get user profile.
     *
     * @throws \RuntimeException
     */
    public static function getUserProfile(string $token): array
    {
        if (self::$fakeProfile !== null) {
            return self::$fakeProfile;
        }

        // Call real Discord API
        $response = \Http::withToken($token)
            ->asJson()
            ->get('https://discord.com/api/users/@me');

        if ($response->failed()) {
            throw new \RuntimeException('Invalid Discord token', 401);
        }

        return $response->json();
    }

    /**
     * Fake the Discord API for testing (just like Http::fake()).
     */
    public static function fake(array $profile): void
    {
        self::$fakeProfile = $profile;
    }

    /**
     * Clear fake profile.
     */
    public static function clearFake(): void
    {
        self::$fakeProfile = null;
    }
}
