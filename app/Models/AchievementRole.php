<?php

namespace App\Models;

use App\Traits\TestableStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="AchievementRole",
 *     @OA\Property(property="lb_format", type="integer"),
 *     @OA\Property(property="lb_type", type="string", enum={"points","no_geraldo","black_border","lccs"}),
 *     @OA\Property(property="threshold", type="integer"),
 *     @OA\Property(property="for_first", type="boolean"),
 *     @OA\Property(property="tooltip_description", type="string", nullable=true),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="clr_border", type="integer"),
 *     @OA\Property(property="clr_inner", type="integer"),
 *     @OA\Property(
 *         property="linked_roles",
 *         type="array",
 *         @OA\Items(
 *             @OA\Property(property="guild_id", type="string"),
 *             @OA\Property(property="role_id", type="string")
 *         )
 *     )
 * )
 */
class AchievementRole extends Model
{
    use HasFactory, TestableStructure;

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null; // Composite key: lb_format, lb_type, threshold
    protected $table = 'achievement_roles';

    protected $fillable = [
        'lb_format',
        'lb_type',
        'threshold',
        'for_first',
        'tooltip_description',
        'name',
        'clr_border',
        'clr_inner',
    ];

    protected $casts = [
        'for_first' => 'boolean',
    ];

    protected static function defaults(array $overrides = []): array
    {
        return array_merge([
            'lb_format' => 1,
            'lb_type' => 'points',
            'threshold' => 0,
            'for_first' => false,
            'tooltip_description' => null,
            'name' => 'Test Achievement Role',
            'clr_border' => 0,
            'clr_inner' => 0,
            'linked_roles' => [],
        ], $overrides);
    }

    protected static function strictFields(): array
    {
        return [
            'lb_format',
            'lb_type',
            'threshold',
            'for_first',
            'tooltip_description',
            'name',
            'clr_border',
            'clr_inner',
            'linked_roles',
        ];
    }
}
