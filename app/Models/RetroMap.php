<?php

namespace App\Models;

use App\Traits\TestableStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="RetroMap",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string")
 * )
 */
class RetroMap extends Model
{
    use HasFactory, TestableStructure;

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

    // -- TestableStructure -- //

    protected static function defaults(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'name' => 'Retro Map',
            'sort_order' => 1,
            'preview_url' => 'https://example.com/preview.jpg',
            'retro_game_id' => 1,
        ], $overrides);
    }

    protected static function strictFields(): array
    {
        return [
            'id',
            'name',
            'sort_order',
            'preview_url',
            'retro_game_id',
        ];
    }
}
