<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public $timestamps = false;
    protected $primaryKey = 'discord_id';
    protected $keyType = 'bigint';
    public $incrementing = false;

    protected $fillable = [
        'discord_id',
        'name',
        'nk_oak',
        'has_seen_popup',
        'is_banned',
    ];

    protected $hidden = [
        'nk_oak',
        'has_seen_popup',
        'pivot',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_seen_popup' => 'boolean',
            'is_banned' => 'boolean',
        ];
    }

    /**
     * Get the user's roles.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    /**
     * Check if user has a specific permission, optionally for a specific format.
     * A permission applies if it's granted globally (format_id = null) or for the specific format.
     */
    public function hasPermission(string $permission, ?int $formatId = null): bool
    {
        return $this->roles()
            ->whereHas('formatPermissions', function ($query) use ($permission, $formatId) {
                $query->where('permission', $permission)
                    ->where(function ($q) use ($formatId) {
                        $q->where('format_id', $formatId)
                            ->orWhereNull('format_id');
                    });
            })
            ->exists();
    }

    /**
     * Get all format IDs where the user has a specific permission.
     *
     * @return array<int> Array of format IDs
     */
    public function formatsWithPermission(string $permission): array
    {
        return $this->roles()
            ->with('formatPermissions')
            ->whereHas('formatPermissions', function ($query) use ($permission) {
                $query->where('permission', $permission);
            })
            ->get()
            ->pluck('formatPermissions.*.format_id')
            ->unique()
            ->flatten()
            ->toArray();
    }

    /**
     * Get the user's permissions accessor.
     */
    protected function getPermissionsAttribute(): array
    {
        return $this->roles()
            ->with('formatPermissions')
            ->get()
            ->pluck('formatPermissions')
            ->flatten()
            ->filter(fn($perm) => $perm->permission !== null)
            ->map(fn($perm) => [
                'permission' => $perm->permission,
                'format_id' => $perm->format_id,
            ])
            ->toArray();
    }

    /**
     * Get the user's completions through the comp_players junction table.
     */
    public function completionMetas(): BelongsToMany
    {
        return $this->belongsToMany(CompletionMeta::class, 'comp_players', 'user_id', 'run');
    }

    /**
     * Get verifications for this user.
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(Verification::class, 'user_id');
    }
}
