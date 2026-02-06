<?php

namespace App\Models;

use App\Traits\TestableStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Map extends Model
{
    use HasFactory, TestableStructure;

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

    // -- TestableStructure -- //

    protected static function defaults(array $overrides = []): array
    {
        return array_merge([
            'code' => 'MLABC123',
            'name' => 'Test Map',
            'placement_allver' => null,
            'placement_curver' => null,
            'difficulty' => null,
            'botb_difficulty' => null,
            'remake_of' => null,
            'r6_start' => null,
            'map_data' => null,
            'optimal_heros' => [],
            'deleted_on' => null,
            'map_preview_url' => null,
            'map_notes' => null,
        ], $overrides);
    }

    protected static function strictFields(): array
    {
        return [
            'code',
            'name',
            'placement_allver',
            'placement_curver',
            'difficulty',
            'botb_difficulty',
            'remake_of',
            'r6_start',
            'map_data',
            'optimal_heros',
            'deleted_on',
            'map_preview_url',
            'map_notes',
        ];
    }
}
