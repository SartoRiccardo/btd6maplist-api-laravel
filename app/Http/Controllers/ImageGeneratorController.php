<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ImageGeneratorController
{
    public function medalBanner($banner)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }
}
