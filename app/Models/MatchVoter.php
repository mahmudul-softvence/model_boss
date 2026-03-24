<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchVoter extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'match_for_voting_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function match()
    {
        return $this->belongsTo(MatchForVoting::class, 'match_for_voting_id');
    }
}
