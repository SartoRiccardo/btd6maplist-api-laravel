<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserController
{
    public function index()
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    public function show($id)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    public function update(Request $request, $id)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    public function banUser(Request $request, $id)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    public function unbanUser(Request $request, $id)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }
}
