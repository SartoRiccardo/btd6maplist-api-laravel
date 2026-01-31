<?php

namespace App\Models;

use App\Traits\TestableStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 *     @OA\Property(property="lcc", type="integer", nullable=true, description="LCC leftover value (if current LCC)"),
 *     @OA\Property(property="subm_proof_img", type="array", items={@OA\Property(type="string")}, description="Image proof URLs"),
 *     @OA\Property(property="subm_proof_vid", type="array", items={@OA\Property(type="string")}, description="Video proof URLs"),
 *     @OA\Property(property="accepted_by", type="string", nullable=true, description="User ID of moderator who accepted"),
 *     @OA\Property(property="created_on", type="integer", description="Unix timestamp of creation"),
 *     @OA\Property(property="deleted_on", type="integer", nullable=true, description="Unix timestamp of deletion (soft delete)"),
 *     @OA\Property(property="subm_notes", type="string", nullable=true, description="Submission notes")
 * )
 */
class CompletionMeta extends Model
{
    use HasFactory, TestableStructure;

    protected $table = 'completions_meta';

    public $timestamps = false;

    protected $hidden = [
        'accepted_by_id',
        'lcc_id',
        'completion_id',
        'copied_from_id',
        'comp_players',
        'proofs',
        'completion',
        'format',
        'acceptedBy',
        'players',
        'copied_from',
        'format_id',
        'copied_to',
    ];

    protected $fillable = [
        'id',
        'completion_id',
        'black_border',
        'no_geraldo',
        'lcc_id',
        'created_on',
        'deleted_on',
        'accepted_by_id',
        'format_id',
        'copied_from_id',
    ];

    protected $appends = [
        'accepted_by',
        'lcc',
    ];

    protected $casts = [
        'accepted_by_id' => 'string',
        'black_border' => 'boolean',
        'no_geraldo' => 'boolean',
        'created_on' => 'timestamp',
        'deleted_on' => 'timestamp',
    ];

    /**
     * Get the completion this metadata belongs to.
     */
    public function completion(): BelongsTo
    {
        return $this->belongsTo(Completion::class);
    }

    /**
     * Get the format of this completion.
     */
    public function format(): BelongsTo
    {
        return $this->belongsTo(Format::class);
    }

    /**
     * Get the user who accepted this completion.
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_id');
    }

    /**
     * Get the LCC data for this completion.
     */
    public function lcc(): HasOne
    {
        return $this->hasOne(LeastCostChimps::class, 'id', 'lcc_id');
    }

    /**
     * Get all players in this completion.
     */
    public function compPlayers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'comp_players', 'run', 'user_id', 'id', 'discord_id');
    }

    /**
     * Alias for compPlayers().
     */
    public function players(): BelongsToMany
    {
        return $this->compPlayers();
    }

    /**
     * Get all proofs for this completion.
     */
    public function proofs(): HasMany
    {
        return $this->hasMany(CompletionProof::class, 'run');
    }

    /**
     * Get accepted_by as an alias for accepted_by_id (for API compatibility).
     */
    public function getAcceptedByAttribute(): ?string
    {
        return $this->attributes['accepted_by_id'] ?? null;
    }

    /**
     * Get lcc as an alias for the lcc relationship data.
     */
    public function getLccAttribute(): ?array
    {
        if (!$this->lcc_id) {
            return null;
        }
        $lcc = $this->getRelationValue('lcc');
        return $lcc?->toArray();
    }

    // -- TestableStructure -- //

    protected static function defaults(array $overrides = []): array
    {
        return array_merge([
            'id' => 0,
            'completion_id' => 0,
            'black_border' => false,
            'no_geraldo' => false,
            'lcc_id' => null,
            'created_on' => now(),
            'deleted_on' => null,
            'accepted_by_id' => null,
            'format_id' => 1,
            'copied_from_id' => null,
        ], $overrides);
    }

    protected static function strictFields(): array
    {
        return [
            'id',
            'completion_id',
            'black_border',
            'no_geraldo',
            'lcc_id',
            'created_on',
            'deleted_on',
            'accepted_by_id',
            'copied_from_id',
        ];
    }
}
