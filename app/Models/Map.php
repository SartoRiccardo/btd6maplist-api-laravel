<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @OA\Schema(
 *     schema="Map",
 *     type="object",
 *     @OA\Property(property="code", type="string", description="Unique map code", example="TKIEXYSQ"),
 *     @OA\Property(property="name", type="string", description="Map name", example="In The Loop"),
 *     @OA\Property(property="r6_start", type="integer", description="BTD6 version when map was added", example=10),
 *     @OA\Property(property="map_data", type="string", nullable=true, description="Map data JSON"),
 *     @OA\Property(property="map_preview_url", type="string", nullable=true, description="URL to map preview image"),
 *     @OA\Property(property="map_notes", type="string", nullable=true, description="Additional notes about the map")
 * )
 */
class Map extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'r6_start',
        'map_data',
        'map_preview_url',
        'map_notes',
    ];

    /**
     * Get the map preview URL, with a default to Ninja Kiwi's data server.
     */
    protected function getMapPreviewUrlAttribute(): ?string
    {
        return $this->attributes['map_preview_url'] ?? "https://data.ninjakiwi.com/btd6/maps/map/{$this->code}/preview";
    }

    /**
     * Get all completions for this map.
     */
    public function completions(): HasMany
    {
        return $this->hasMany(Completion::class, 'map_code');
    }

    /**
     * Get all creators for this map.
     */
    public function creators(): HasMany
    {
        return $this->hasMany(Creator::class, 'map_code');
    }

    /**
     * Get all verifications for this map.
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(Verification::class, 'map_code');
    }

    /**
     * Get all additional codes for this map.
     */
    public function additionalCodes(): HasMany
    {
        return $this->hasMany(AdditionalCode::class, 'belongs_to', 'code');
    }

    /**
     * Get all aliases for this map.
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(MapAlias::class, 'map_code');
    }

    /**
     * Get all compatibilities for this map.
     */
    public function compatibilities(): HasMany
    {
        return $this->hasMany(MapverCompatibility::class, 'map_code');
    }
}
