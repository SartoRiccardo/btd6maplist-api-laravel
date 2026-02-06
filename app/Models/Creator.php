<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Creator extends Pivot
{
    use HasFactory;

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $table = 'creators';

    protected $fillable = [
        'user_id',
        'map_code',
        'role',
    ];

    /**
     * Get the user who created this map.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'discord_id');
    }

    /**
     * Get the map.
     */
    public function map()
    {
        return $this->belongsTo(Map::class, 'map_code', 'code');
    }
}
