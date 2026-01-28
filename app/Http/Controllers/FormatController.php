<?php

namespace App\Http\Controllers;

use App\Models\Format;
use Illuminate\Http\JsonResponse;

class FormatController extends Controller
{
    /**
     * Returns a list of all available formats. No authentication required.
     *
     * @OA\Get(
     *     path="/formats",
     *     summary="Get all formats",
     *     description="Returns a list of all available formats. No authentication required.",
     *     tags={"Formats"},
     *     @OA\Response(
     *         response=200,
     *         description="Array of formats",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Format")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $formats = Format::orderBy('id')->get();

        return response()->json($formats);
    }
}
