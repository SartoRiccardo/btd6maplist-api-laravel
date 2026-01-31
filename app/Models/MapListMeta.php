<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapListMeta extends Model
{
    use HasFactory;

    protected $table = 'map_list_meta';

    public $timestamps = false;

    protected $fillable = [
        'code',
        'placement_curver',
        'placement_allver',
        'difficulty',
        'optimal_heros',
        'botb_difficulty',
        'remake_of',
        'created_on',
        'deleted_on',
    ];

    protected $casts = [
        'optimal_heros' => 'array', // PostgreSQL text[] array
    ];

    /**
     * Get the map for this meta.
     */
    public function map(): BelongsTo
    {
        return $this->belongsTo(Map::class, 'code', 'code');
    }
}
