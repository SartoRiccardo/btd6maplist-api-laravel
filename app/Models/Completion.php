<?php

namespace App\Models;

use App\Constants\ProofType;
use App\Traits\TestableStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @OA\Schema(
 *     schema="Completion",
 *     type="object",
 *     @OA\Property(property="id", type="integer", description="The completion ID"),
 *     @OA\Property(property="map", type="string", description="Map code"),
 *     @OA\Property(property="users", type="array", items={@OA\Property(ref="#/components/schemas/PartialUser")}, description="Users who completed the run"),
 *     @OA\Property(property="black_border", type="boolean", description="Whether the run was black border"),
 *     @OA\Property(property="no_geraldo", type="boolean", description="Whether the run was no optimal hero"),
 *     @OA\Property(property="current_lcc", type="boolean", description="Whether this is the current LCC for the map"),
 *     @OA\Property(property="format", type="integer", description="Format ID"),
 *     @OA\Property(property="lcc", type="object", nullable=true,
 *         @OA\Property(property="leftover", type="integer", description="LCC leftover value"),
 *     ),
 *     @OA\Property(property="subm_proof_img", type="array", items={@OA\Property(type="string")}, description="Image proof URLs"),
 *     @OA\Property(property="subm_proof_vid", type="array", items={@OA\Property(type="string")}, description="Video proof URLs"),
 *     @OA\Property(property="accepted_by", type="string", nullable=true, description="User ID of moderator who accepted"),
 *     @OA\Property(property="created_on", type="integer", description="Unix timestamp of creation"),
 *     @OA\Property(property="deleted_on", type="integer", nullable=true, description="Unix timestamp of deletion (soft delete)"),
 *     @OA\Property(property="subm_notes", type="string", nullable=true, description="Submission notes")
 * )
 */
class Completion extends Model
{
    use HasFactory, TestableStructure;

    public $timestamps = false;

    protected $appends = [
        'subm_proof_img',
        'subm_proof_vid',
    ];

    protected $hidden = [
        'submitted_on',
        'subm_wh_payload',
        'copied_from_id',
        'proofs',
        'completionMetas',
        'meta',
        'latestMeta',
        'map_code',
    ];

    protected $fillable = [
        'id',
        'map_code',
        'submitted_on',
        'subm_notes',
        'subm_wh_payload',
        'copied_from_id',
    ];

    protected $casts = [
        'submitted_on' => 'timestamp',
        'subm_wh_payload' => 'array', // JSON decode webhook payload
    ];

    /**
     * Get the latest (current) metadata for this completion.
     * This uses the latest_completions view logic.
     */
    public function latestMeta(): HasOne
    {
        return $this->hasOne(CompletionMeta::class, 'completion_id')
            ->orderBy('created_on', 'desc')
            ->orderBy('id', 'desc');
    }

    /**
     * Get the map for this completion.
     */
    public function map(): BelongsTo
    {
        return $this->belongsTo(Map::class, 'map_code', 'code');
    }

    /**
     * Get all proofs for this completion.
     */
    public function proofs(): HasMany
    {
        return $this->hasMany(CompletionProof::class, 'run');
    }

    /**
     * Get image proof URLs for API responses.
     */
    public function getSubmProofImgAttribute(): array
    {
        if (!$this->relationLoaded('proofs')) {
            $this->load('proofs');
        }
        return $this->proofs->where('proof_type', ProofType::IMAGE)->pluck('proof_url')->values()->toArray();
    }

    /**
     * Get video proof URLs for API responses.
     */
    public function getSubmProofVidAttribute(): array
    {
        if (!$this->relationLoaded('proofs')) {
            $this->load('proofs');
        }
        return $this->proofs->where('proof_type', ProofType::VIDEO)->pluck('proof_url')->values()->toArray();
    }

    // -- TestableStructure -- //

    protected static function defaults(array $overrides = []): array
    {
        return array_merge([
            'id' => 0,
            'users' => [],
            'black_border' => false,
            'no_geraldo' => false,
            'current_lcc' => false,
            'format' => 1,
            'lcc' => null,
            'subm_proof_img' => [],
            'subm_proof_vid' => [],
            'accepted_by' => null,
            'created_on' => null,
            'deleted_on' => null,
            'subm_notes' => null,
        ], $overrides);
    }

    protected static function strictFields(): array
    {
        return [
            'id',
            'map',
            'users',
            'black_border',
            'no_geraldo',
            'current_lcc',
            'format',
            'lcc',
            'subm_proof_img',
            'subm_proof_vid',
            'accepted_by',
            'created_on',
            'deleted_on',
            'subm_notes',
        ];
    }
}
