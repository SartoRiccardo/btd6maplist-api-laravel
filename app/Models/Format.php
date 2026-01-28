<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Format extends Model
{
    use HasFactory;

    public $timestamps = false;

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

    protected $casts = [
        'id' => 'integer',
        'hidden' => 'boolean',
        'run_submission_status' => 'integer',
        'map_submission_status' => 'integer',
    ];

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
