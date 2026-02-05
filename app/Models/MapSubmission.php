<?php

namespace App\Models;

use App\Traits\TestableStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="MapSubmission",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="code", type="string"),
 *     @OA\Property(property="submitter_id", type="string", description="Discord ID of submitter"),
 *     @OA\Property(property="subm_notes", type="string", nullable=true),
 *     @OA\Property(property="format_id", type="integer"),
 *     @OA\Property(property="proposed", type="integer", nullable=true, description="Proposed difficulty"),
 *     @OA\Property(property="rejected_by", type="string", nullable=true, description="Discord ID of rejecter"),
 *     @OA\Property(property="created_on", type="integer", description="Unix timestamp"),
 *     @OA\Property(property="completion_proof", type="string")
 * )
 */
class MapSubmission extends Model
{
    use HasFactory, TestableStructure;

    public $timestamps = false;

    protected $fillable = [
        'code',
        'submitter_id',
        'subm_notes',
        'format_id',
        'proposed',
        'rejected_by',
        'created_on',
        'completion_proof',
        'wh_data',
        'wh_msg_id',
    ];

    protected $casts = [
        'created_on' => 'timestamp',
    ];

    /**
     * Get the user who submitted this map.
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitter_id', 'discord_id');
    }

    /**
     * Get the user who rejected this submission (if any).
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by', 'discord_id');
    }

    /**
     * Get the format for this submission.
     */
    public function format(): BelongsTo
    {
        return $this->belongsTo(Format::class);
    }

    // -- TestableStructure -- //

    protected static function defaults(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'code' => 'MLTEST01',
            'submitter' => '123456789',
            'subm_notes' => null,
            'format_id' => 1,
            'proposed' => null,
            'rejected_by' => null,
            'created_on' => now(),
            'completion_proof' => 'https://example.com/proof.jpg',
            'wh_data' => null,
            'wh_msg_id' => null,
        ], $overrides);
    }

    protected static function strictFields(): array
    {
        return [
            'id',
            'code',
            'submitter',
            'subm_notes',
            'format_id',
            'proposed',
            'rejected_by',
            'created_on',
            'completion_proof',
            'wh_data',
            'wh_msg_id',
        ];
    }
}
