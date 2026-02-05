<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdditionalCode extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'code',
        'description',
        'belongs_to',
    ];

    /**
     * Get the map this additional code belongs to.
     */
    public function map()
    {
        return $this->belongsTo(Map::class, 'belongs_to', 'code');
    }
}
