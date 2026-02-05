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
        'game_id',
        'category_id',
        'subcategory_id',
    ];

    /**
     * Get the retro game this map belongs to.
     * Note: This is a composite key relationship.
     */
    public function game()
    {
        return $this->belongsTo(RetroGame::class, null, null, null)
            ->whereColumn('retro_games.game_id', 'retro_maps.game_id')
            ->whereColumn('retro_games.category_id', 'retro_maps.category_id')
            ->whereColumn('retro_games.subcategory_id', 'retro_maps.subcategory_id');
    }

    // -- TestableStructure -- //

    protected static function defaults(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'name' => 'Retro Map',
            'sort_order' => 1,
            'preview_url' => 'https://example.com/preview.jpg',
            'game_id' => 1,
            'category_id' => 1,
            'subcategory_id' => 1,
        ], $overrides);
    }

    protected static function strictFields(): array
    {
        return [
            'id',
            'name',
            'sort_order',
            'preview_url',
            'game_id',
            'category_id',
            'subcategory_id',
        ];
    }
}
