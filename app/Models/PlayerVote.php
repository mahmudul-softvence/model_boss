<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class PlayerVote extends Model
{
    protected $fillable = [
        'user_id',
        'voted_player_id',
        'match_id',
        'total_vote',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
}
