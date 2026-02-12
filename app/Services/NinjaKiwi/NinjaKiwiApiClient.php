<?php

namespace App\Services\NinjaKiwi;

class NinjaKiwiApiClient
{
    protected static ?array $fakeDeco = null;

    /**
     * Get BTD6 user decoration (avatar and banner URLs) from Ninja Kiwi API.
     *
     * @param string $oak The user's OAK (OpenAPI Key)
     * @return array Array with 'avatarURL' and 'bannerURL' keys (null if not found)
     */
    public static function getBtd6UserDeco(string $oak): array
    {
        if (self::$fakeDeco !== null) {
            return self::$fakeDeco;
        }

        $response = \Http::get("https://data.ninjakiwi.com/btd6/users/{$oak}");

        if ($response->failed() || !$response->json('success')) {
            return ['avatarURL' => null, 'bannerURL' => null];
        }

        $body = $response->json('body', []);

        return [
            'avatar_url' => $body['avatarURL'] ?? null,
            'banner_url' => $body['bannerURL'] ?? null,
        ];
    }

    /**
     * Fake the Ninja Kiwi API for testing.
     *
     * @param array $deco Array with 'avatarURL' and 'bannerURL' keys
     */
    public static function fake(array $deco): void
    {
        self::$fakeDeco = $deco;
    }

    /**
     * Clear fake data.
     */
    public static function clearFake(): void
    {
        self::$fakeDeco = null;
    }
}
