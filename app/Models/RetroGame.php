<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetroGame extends Model
{
    use HasFactory;

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
}
