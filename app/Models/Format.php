<?php

namespace App\Models;

use App\Constants\FormatConstants;
use App\Casts\MapSubmissionStatusCast;
use App\Casts\RunSubmissionStatusCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Format",
 *     type="object",
 *     @OA\Property(property="id", type="integer", description="The ID of the format"),
 *     @OA\Property(property="name", type="string", description="The name of the format"),
 *     @OA\Property(property="hidden", type="boolean", description="Whether maps with this format should be hidden"),
 *     @OA\Property(property="map_submission_status", type="string", enum={"closed", "open", "open_chimps"}, description="Whether map submissions are open, closed, or open_chimps"),
 *     @OA\Property(property="run_submission_status", type="string", enum={"closed", "open", "lcc_only"}, description="Whether run submissions are open, closed, or lcc_only"),
 *     @OA\Property(property="proposed_difficulties", type="array", items={ "type"="string" }, nullable=true, description="What difficulties can be proposed by map submitters")
 * )
 */
class Format extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $hidden = [
        'map_submission_wh',
        'run_submission_wh',
        'emoji',
    ];

    protected $fillable = [
        'id',
        'name',
        'map_submission_wh',
        'run_submission_wh',
        'hidden',
        'run_submission_status',
        'map_submission_status',
        'emoji',
    ];

    public $incrementing = false;

    protected $appends = [
        'proposed_difficulties',
    ];

    protected $casts = [
        'id' => 'integer',
        'hidden' => 'boolean',
        'run_submission_status' => RunSubmissionStatusCast::class,
        'map_submission_status' => MapSubmissionStatusCast::class,
    ];

    public function getProposedDifficultiesAttribute(): ?array
    {
        return match ($this->id) {
            FormatConstants::MAPLIST, FormatConstants::MAPLIST_ALL_VERSIONS => ["Top 3", "Top 10", "#11 ~ 20", "#21 ~ 30", "#31 ~ 40", "#41 ~ 50"],
            FormatConstants::EXPERT_LIST => ["Casual Expert", "Casual/Medium Expert", "Medium Expert", "Medium/High Expert", "High Expert", "High/True Expert", "True Expert", "True/Extreme Expert", "Extreme Expert"],
            FormatConstants::BEST_OF_THE_BEST => ["Beginner", "Intermediate", "Advanced", "Expert/Extreme"],
            default => null,
        };
    }

    public function configFormats()
    {
        return $this->hasMany(ConfigFormat::class, 'format_id');
    }

    public function roleFormatPermissions()
    {
        return $this->hasMany(RoleFormatPermission::class, 'format_id');
    }

    public function formatRulesSubsets()
    {
        return $this->belongsToMany(Format::class, 'formats_rules_subsets', 'format_parent', 'format_child');
    }
}
