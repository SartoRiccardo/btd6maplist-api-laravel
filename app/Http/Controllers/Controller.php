<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     type="object",
 *     @OA\Property(property="current_page", type="integer", description="Current page number", example=1),
 *     @OA\Property(property="last_page", type="integer", description="Last page number", example=5),
 *     @OA\Property(property="per_page", type="integer", description="Number of items per page", example=100),
 *     @OA\Property(property="total", type="integer", description="Total number of items", example=450)
 * )
 */
abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;
}
