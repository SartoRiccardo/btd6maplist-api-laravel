<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SearchController
{
    public function search(Request $request)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }
}
