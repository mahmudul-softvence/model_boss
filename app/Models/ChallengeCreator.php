<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChallengeCreator extends Model
{
    protected $table = 'challenge_creators';

    protected $fillable = [
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
