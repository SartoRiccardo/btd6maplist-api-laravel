<?php

namespace App\Services;

use App\Services\NinjaKiwi\NinjaKiwiApiClient;

class UserService
{
    /**
     * Get user decoration (avatar and banner URLs) from Ninja Kiwi.
     *
     * @param string $nkOak User's Ninja Kiwi OAK
     * @return array|null Array with 'avatar_url' and 'banner_url', or null if not found
     */
    public function getUserDeco(string $nkOak): ?array
    {
        return NinjaKiwiApiClient::getBtd6UserDeco($nkOak);
    }
}
