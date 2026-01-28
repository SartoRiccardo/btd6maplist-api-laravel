<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleFormatPermission extends Model
{
    use HasFactory;

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = [
        'role_id',
        'format_id',
        'permission',
    ];

    protected $casts = [
        'role_id' => 'integer',
        'format_id' => 'integer',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function format()
    {
        return $this->belongsTo(Format::class, 'format_id');
    }
}
