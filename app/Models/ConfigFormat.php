<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigFormat extends Model
{
    use HasFactory;

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = [
        'config_name',
        'format_id',
    ];

    protected $casts = [
        'format_id' => 'integer',
    ];

    public function config()
    {
        return $this->belongsTo(Config::class, 'config_name', 'name');
    }

    public function format()
    {
        return $this->belongsTo(Format::class, 'format_id');
    }
}
