<?php

namespace App\Models;

use App\Traits\TestableStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetroGame extends Model
{
    use HasFactory, TestableStructure;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'game_id',
        'category_id',
        'subcategory_id',
        'game_name',
        'category_name',
        'subcategory_name',
    ];

    /**
     * Get retro maps for this game.
     */
    public function retroMaps(): HasMany
    {
        return $this->hasMany(RetroMap::class, 'retro_game_id');
    }

    // -- TestableStructure -- //

    protected static function defaults(array $overrides = []): array
    {
        return array_merge([
            'game_id' => 1,
            'category_id' => 1,
            'subcategory_id' => 1,
            'game_name' => 'BTD6',
            'category_name' => 'Beginner',
            'subcategory_name' => null,
        ], $overrides);
    }

    protected static function strictFields(): array
    {
        return [
            'game_id',
            'category_id',
            'subcategory_id',
            'game_name',
            'category_name',
            'subcategory_name',
        ];
    }
}
