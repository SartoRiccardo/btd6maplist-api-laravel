<?php

namespace App\Services\Discord;

class DiscordApiClient
{
    protected static ?array $fakeProfile = null;
    protected static bool $fakeFailure = false;

    /**
     * Validate Discord token and get user profile.
     *
     * @throws \RuntimeException
     */
    public static function getUserProfile(string $token): array
    {
        if (self::$fakeFailure) {
            throw new \RuntimeException('Invalid Discord token', 401);
        }

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
        self::$fakeFailure = false;
    }

    /**
     * Fake the Discord API returning a failure.
     */
    public static function fakeFailure(): void
    {
        self::$fakeFailure = true;
        self::$fakeProfile = null;
    }

    /**
     * Clear fake profile.
     */
    public static function clearFake(): void
    {
        self::$fakeProfile = null;
        self::$fakeFailure = false;
    }
}
