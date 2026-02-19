<?php

namespace App\Models;

use App\Traits\TestableStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Verification extends Model
{
    use HasFactory, TestableStructure;

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = [
        'map_code',
        'user_id',
        'version',
    ];

    protected $casts = [
        'version' => 'integer',
        'user_id' => 'string',
    ];

    protected $hidden = [
        'map_code',
    ];

    /**
     * Get the map for this verification.
     */
    public function map(): BelongsTo
    {
        return $this->belongsTo(Map::class, 'map_code', 'code');
    }

    /**
     * Get the user who verified this map.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get verified map codes for a specific version, filtered by map codes.
     *
     * @param int $version The BTD6 version
     * @param iterable $mapCodes Collection of map codes to filter by
     * @return \Illuminate\Support\Collection Collection of verified map codes
     */
    public static function getVerifiedMapCodes(int $version, iterable $mapCodes)
    {
        return self::where(function ($q) use ($version) {
            $q->where('version', $version)
                ->orWhereNull('version');
        })
            ->whereIn('map_code', $mapCodes)
            ->distinct()
            ->pluck('map_code');
    }

    // --- TestableStructure --- //

    /**
     * Get the default values for the Verification JSON structure.
     */
    protected static function defaults(array $overrides = []): array
    {
        return [
            'map_code' => $overrides['map_code'] ?? 'TESTCODE',
            'user_id' => $overrides['user_id'] ?? '123456789012345678',
            'version' => $overrides['version'] ?? null,
            'user' => [],
        ];
    }

    /**
     * Get the fields that are allowed when strict mode is enabled.
     */
    protected static function strictFields(): array
    {
        return [
            'map_code',
            'user_id',
            'version',
            'user',
            'avatar_url',
            'banner_url',
        ];
    }
}
