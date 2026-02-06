<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CompletionController
{
    public function index()
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    public function show($id)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    public function submit(Request $request)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    public function transfer(Request $request)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    public function save(Request $request)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    public function update(Request $request, $id)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    public function destroy($id)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }
}
