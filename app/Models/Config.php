<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $table = 'config';

    protected $fillable = [
        'name',
        'value',
        'type',
        'new_version',
        'created_on',
        'difficulty',
        'description',
    ];

    protected $casts = [
        'difficulty' => 'integer',
        'new_version' => 'integer',
    ];

    public function configFormats()
    {
        return $this->hasMany(ConfigFormat::class, 'config_name', 'name');
    }
}
