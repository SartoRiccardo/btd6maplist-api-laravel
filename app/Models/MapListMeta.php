<?php

namespace App\Models;

use App\Constants\FormatConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    protected $hidden = [
        'created_on',
        'id',
    ];

    protected $casts = [
        'optimal_heros' => 'array'
    ];

    /**
     * Get the map for this meta.
     */
    public function map(): BelongsTo
    {
        return $this->belongsTo(Map::class, 'code', 'code');
    }

    /**
     * Get the retro map this meta remakes.
     */
    public function retroMap(): HasOne
    {
        return $this->hasOne(RetroMap::class, 'id', 'remake_of');
    }

    /**
     * Scope to filter by format_id.
     */
    public function scopeForFormat($query, ?int $formatId)
    {
        if (!$formatId) {
            return $query;
        }

        // Get format map count from config
        $mapCount = Config::loadVars(['map_count'])->get('map_count', 50);

        return match ($formatId) {
            FormatConstants::MAPLIST => $query->whereBetween('placement_curver', [1, $mapCount])
                ->orderBy('placement_curver', 'asc'),
            FormatConstants::MAPLIST_ALL_VERSIONS => $query->whereBetween('placement_allver', [1, $mapCount])
                ->orderBy('placement_allver', 'asc'),
            FormatConstants::EXPERT_LIST => $query->whereNotNull('difficulty')
                ->orderBy('difficulty', 'asc'),
            FormatConstants::BEST_OF_THE_BEST => $query->whereNotNull('botb_difficulty')
                ->orderBy('botb_difficulty', 'asc'),
            FormatConstants::NOSTALGIA_PACK => $query->whereNotNull('remake_of'),

            default => $query, // Unknown format, don't filter
        };
    }
}
