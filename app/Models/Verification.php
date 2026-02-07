<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Verification extends Model
{
    use HasFactory;

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
}
