<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Map extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'r6_start',
        'map_data',
        'map_preview_url',
        'map_notes',
    ];

    protected $casts = [
        'r6_start' => 'datetime',
    ];

    /**
     * Get all completions for this map.
     */
    public function completions(): HasMany
    {
        return $this->hasMany(Completion::class, 'map');
    }
}
