<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MapAlias extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $primaryKey = null;

    public $incrementing = false;

    protected $fillable = [
        'alias',
        'map_code',
    ];

    /**
     * Get the map for this alias.
     */
    public function map()
    {
        return $this->belongsTo(Map::class, 'map_code', 'code');
    }
}
