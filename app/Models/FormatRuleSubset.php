<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class FormatRuleSubset extends Pivot
{
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = [
        'format_parent',
        'format_child',
    ];

    protected $casts = [
        'format_parent' => 'integer',
        'format_child' => 'integer',
    ];

    public function parentFormat()
    {
        return $this->belongsTo(Format::class, 'format_parent');
    }

    public function childFormat()
    {
        return $this->belongsTo(Format::class, 'format_child');
    }
}
