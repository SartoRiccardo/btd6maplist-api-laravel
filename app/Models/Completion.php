<?php

namespace App\Models;

use App\Constants\ProofType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Completion extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $appends = [
        'subm_proof_img',
        'subm_proof_vid',
        'created_on',
    ];

    protected $hidden = [
        'submitted_on',
        'subm_wh_payload',
        'copied_from_id',
        'proofs',
        'completionMetas',
        'meta',
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
    ];

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

    /**
     * Get created_on timestamp (submitted_on) for API responses.
     */
    public function getCreatedOnAttribute(): ?int
    {
        return $this->submitted_on;
    }
}
