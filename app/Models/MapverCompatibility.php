<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MapverCompatibility extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'map_code',
        'status',
        'version',
    ];

    /**
     * Get the map.
     */
    public function map()
    {
        return $this->belongsTo(Map::class, 'map_code', 'code');
    }
}
