<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscordRole extends Model
{
    use HasFactory;

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = 'role_id';
    protected $table = 'discord_roles';
    protected $keyType = 'bigint';

    protected $fillable = [
        'ar_lb_format',
        'ar_lb_type',
        'ar_threshold',
        'guild_id',
        'role_id',
    ];

    protected $casts = [
        'role_id' => 'string',
        'guild_id' => 'string',
    ];

    protected $hidden = [
        'ar_lb_format',
        'ar_lb_type',
        'ar_threshold',
    ];

    /**
     * Get the achievement role that owns this Discord role.
     */
    public function achievementRole()
    {
        return $this->belongsTo(AchievementRole::class, 'ar_lb_format', 'lb_format');
    }
}
