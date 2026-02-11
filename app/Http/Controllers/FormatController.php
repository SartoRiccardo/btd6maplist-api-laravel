<?php

namespace App\Http\Controllers;

use App\Http\Requests\Format\IndexFormatRequest;
use App\Models\Format;
use Illuminate\Http\Request;

class FormatController
{
    /**
     * Get a paginated list of formats.
     *
     * @OA\Get(
     *     path="/formats",
     *     summary="Get list of formats",
     *     description="Retrieves a paginated list of all available formats. Webhook URLs and emoji are excluded from the response for security.",
     *     tags={"Formats"},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexFormatRequest/properties/page")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexFormatRequest/properties/per_page")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Format")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(IndexFormatRequest $request)
    {
        $validated = $request->validated();

        $page = $validated['page'];
        $perPage = $validated['per_page'];

        $formats = Format::query()
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $formats->items(),
            'meta' => [
                'current_page' => $formats->currentPage(),
                'last_page' => $formats->lastPage(),
                'per_page' => $formats->perPage(),
                'total' => $formats->total(),
            ],
        ]);
    }

    public function show($id)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    public function update(Request $request)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    public function leaderboard($id)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }
}
