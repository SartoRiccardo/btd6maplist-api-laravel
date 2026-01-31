<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Map extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $hidden = [
        'latestMeta',
    ];

    protected $fillable = [
        'code',
        'name',
        'r6_start',
        'map_data',
        'map_preview_url',
        'map_notes',
    ];

    /**
     * Get all completions for this map.
     */
    public function completions(): HasMany
    {
        return $this->hasMany(Completion::class, 'map_code');
    }

    /**
     * Get the latest (current) metadata for this map.
     */
    public function latestMeta(): HasOne
    {
        return $this->hasOne(MapListMeta::class, 'code', 'code')
            ->whereNull('deleted_on')
            ->orderBy('created_on', 'desc')
            ->orderBy('id', 'desc');
    }
}
