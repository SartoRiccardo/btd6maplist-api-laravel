<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompPlayer extends Model
{
    use HasFactory;

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $table = 'comp_players';

    protected $fillable = [
        'user_id',
        'run',
    ];

    /**
     * Get the completion metadata this player belongs to.
     */
    public function completionMeta()
    {
        return $this->belongsTo(CompletionMeta::class, 'run');
    }

    /**
     * Get the user.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
