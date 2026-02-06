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
        return response()->json(['message' => 'Not Implemented'], 501);
    }
}
