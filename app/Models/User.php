<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @OA\Schema(
 *     schema="UserProfile",
 *     @OA\Property(property="id", type="string", example="2000000"),
 *     @OA\Property(property="name", type="string", example="test_new_usr"),
 *     @OA\Property(property="oak", type="string", nullable=true),
 *     @OA\Property(property="has_seen_popup", type="boolean", example=false),
 *     @OA\Property(property="is_banned", type="boolean", example=false),
 *     @OA\Property(
 *         property="permissions",
 *         type="array",
 *         @OA\Items(type="object")
 *     ),
 *     @OA\Property(
 *         property="roles",
 *         type="array",
 *         @OA\Items(type="object")
 *     ),
 *     @OA\Property(
 *         property="completions",
 *         type="array",
 *         @OA\Items(type="object")
 *     )
 * )
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public $timestamps = false;
    protected $primaryKey = 'discord_id';
    protected $keyType = 'bigint';
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'discord_id',
        'name',
        'nk_oak',
        'has_seen_popup',
        'is_banned',
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
     * Get the user's completions.
     */
    public function completions(): HasMany
    {
        return $this->hasMany(Completion::class, 'player_id');
    }

    /**
     * Convert the model to its array representation for API responses.
     */
    public function toArray(): array
    {
        return [
            'id' => (string) $this->discord_id,
            'name' => $this->name,
            'oak' => $this->nk_oak,
            'has_seen_popup' => $this->has_seen_popup,
            'is_banned' => $this->is_banned,
            'permissions' => $this->permissions ?? [], // TODO: implement
            'roles' => $this->relationLoaded('roles') ? $this->roles->toArray() : [],
            'completions' => $this->relationLoaded('completions') ? $this->completions->toArray() : [],
        ];
    }
}
