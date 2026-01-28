<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleGrant extends Model
{
    use HasFactory;

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = [
        'role_required',
        'role_can_grant',
    ];

    protected $casts = [
        'role_required' => 'integer',
        'role_can_grant' => 'integer',
    ];

    public function requiredRole()
    {
        return $this->belongsTo(Role::class, 'role_required');
    }

    public function grantedRole()
    {
        return $this->belongsTo(Role::class, 'role_can_grant');
    }
}
