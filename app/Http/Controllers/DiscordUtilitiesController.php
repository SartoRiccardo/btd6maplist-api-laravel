<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DiscordUtilitiesController
{
    public function serverRoles(Request $request)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }
}
