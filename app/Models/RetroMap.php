<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="RetroMap",
 *     type="object",
 *     @OA\Property(property="id", type="integer", description="Retro map ID", example=82),
 *     @OA\Property(property="name", type="string", description="Retro map name", example="doloremque architecto molestias"),
 *     @OA\Property(property="sort_order", type="integer", description="Sort order", example=82),
 *     @OA\Property(property="preview_url", type="string", description="Preview image URL", example="http://www.little.info/"),
 *     @OA\Property(property="retro_game_id", type="integer", description="ID of the retro game", example=1),
 *     @OA\Property(property="game", ref="#/components/schemas/RetroGame")
 * )
 */
class RetroMap extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'sort_order',
        'preview_url',
        'retro_game_id',
    ];

    /**
     * Get the retro game this map belongs to.
     */
    public function game()
    {
        return $this->belongsTo(RetroGame::class, 'retro_game_id');
    }
}
