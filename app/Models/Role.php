<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'assign_on_create',
        'internal',
    ];

    public $incrementing = false;

    protected $casts = [
        'id' => 'integer',
        'assign_on_create' => 'boolean',
        'internal' => 'boolean',
    ];

    protected $appends = [
        'can_grant',
    ];

    protected $hidden = [
        'canGrant',
        'assign_on_create',
    ];

    public function canGrant()
    {
        return $this->belongsToMany(Role::class, 'role_grants', 'role_required', 'role_can_grant');
    }

    public function grantedBy()
    {
        return $this->belongsToMany(Role::class, 'role_grants', 'role_can_grant', 'role_required');
    }

    public function formatPermissions()
    {
        return $this->hasMany(RoleFormatPermission::class, 'role_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    public function getCanGrantAttribute(): array
    {
        if ($this->relationLoaded('canGrant')) {
            $relation = $this->getRelationValue('canGrant');
            return $relation ? $relation->pluck('id')->toArray() : [];
        }
        return [];
    }
}
