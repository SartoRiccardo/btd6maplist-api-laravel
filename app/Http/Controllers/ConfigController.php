<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ConfigController
{
    public function index()
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    public function update(Request $request)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }
}
