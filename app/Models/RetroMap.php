<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
