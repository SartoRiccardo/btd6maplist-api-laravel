<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="LCC",
 *     type="object",
 *     @OA\Property(property="id", type="integer", description="The LCC ID"),
 *     @OA\Property(property="leftover", type="integer", description="Cash leftover at end of run")
 * )
 */
class LeastCostChimps extends Model
{
    use HasFactory;

    protected $table = 'leastcostchimps';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'leftover',
    ];

    /**
     * Get all completion metadata that use this LCC.
     */
    public function completionMetas()
    {
        return $this->hasMany(CompletionMeta::class, 'lcc');
    }
}
