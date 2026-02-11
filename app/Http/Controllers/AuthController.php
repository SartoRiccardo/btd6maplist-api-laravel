<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController
{
    public function authenticate(Request $request)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    public function readRules(Request $request)
    {
        $user = auth()->guard('discord')->user();
        $user->has_seen_popup = true;
        $user->save();

        return response()->noContent();
    }
}
